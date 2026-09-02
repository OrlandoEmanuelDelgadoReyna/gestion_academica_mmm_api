<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Miembro;
use App\Models\PasswordResetCode;
use App\Models\Usuario;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\PasswordRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class PasswordRecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NuevaClaveSegura12';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_existing_email_returns_generic_200(): void
    {
        $this->createUserWithEmail('ruth@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'ruth@correo.com',
        ])
            ->assertOk()
            ->assertExactJson(['message' => PasswordRecoveryService::REQUEST_MESSAGE]);
    }

    public function test_unknown_email_returns_generic_200(): void
    {
        $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'nadie@correo.com',
        ])
            ->assertOk()
            ->assertExactJson(['message' => PasswordRecoveryService::REQUEST_MESSAGE]);
    }

    public function test_existing_and_unknown_email_responses_are_indistinguishable(): void
    {
        $this->createUserWithEmail('existe@correo.com');

        $existing = $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'existe@correo.com',
        ]);
        $unknown = $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'noexiste@correo.com',
        ]);

        $this->assertSame($existing->status(), $unknown->status());
        $this->assertSame($existing->json(), $unknown->json());
        $this->assertSame(array_keys($existing->json()), array_keys($unknown->json()));
    }

    public function test_request_generates_hashed_code_and_omits_it_from_json(): void
    {
        $usuario = $this->createUserWithEmail('codigo@correo.com');
        $codigo = $this->requestCodeFor('codigo@correo.com');

        $this->assertNotNull($codigo);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);
        $this->assertDatabaseCount('password_reset_codes', 1);

        $row = PasswordResetCode::query()->firstOrFail();
        $this->assertSame($usuario->id, $row->usuario_id);
        $this->assertNotSame($codigo, $row->codigo_hash);
        $this->assertTrue(Hash::check($codigo, $row->codigo_hash));
        $this->assertNull($row->used_at);
        $this->assertSame(0, $row->attempts);
    }

    public function test_valid_code_resets_password_revokes_tokens_and_does_not_issue_a_token(): void
    {
        $usuario = $this->createUserWithEmail('ok@correo.com');
        $usuario->createToken('phone');
        $usuario->createToken('web');
        $this->assertSame(2, PersonalAccessToken::query()->where('tokenable_id', $usuario->id)->count());

        $codigo = $this->requestCodeFor('ok@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', [
            'correo_electronico' => 'ok@correo.com',
            'codigo' => $codigo,
            'contrasena' => self::NEW_PASSWORD,
            'contrasena_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('message', PasswordRecoveryService::SUCCESS_MESSAGE)
            ->assertJsonMissingPath('token');

        $usuario->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $usuario->contrasena));
        $this->assertFalse(Hash::check('OldPassword1234', $usuario->contrasena));
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $usuario->id)->count());
        $this->assertNotNull(PasswordResetCode::query()->first()?->used_at);
    }

    public function test_wrong_code_returns_generic_422_and_increments_attempts(): void
    {
        $this->createUserWithEmail('fail@correo.com');
        $this->requestCodeFor('fail@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('fail@correo.com', '000000'))
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', PasswordRecoveryService::INVALID_CODE_MESSAGE);

        $this->assertSame(1, PasswordResetCode::query()->value('attempts'));
    }

    public function test_fifth_failed_attempt_blocks_the_request_including_the_real_code(): void
    {
        $this->createUserWithEmail('lock@correo.com');
        $codigo = $this->requestCodeFor('lock@correo.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('lock@correo.com', '111111'))
                ->assertStatus(422);
        }

        $this->assertSame(5, PasswordResetCode::query()->value('attempts'));

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('lock@correo.com', $codigo))
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', PasswordRecoveryService::INVALID_CODE_MESSAGE);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->createUserWithEmail('exp@correo.com');
        $codigo = $this->requestCodeFor('exp@correo.com');

        $this->travel(11)->minutes();

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('exp@correo.com', $codigo))
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', PasswordRecoveryService::INVALID_CODE_MESSAGE);
    }

    public function test_used_code_cannot_be_reused_even_immediately(): void
    {
        $this->createUserWithEmail('reuse@correo.com');
        $codigo = $this->requestCodeFor('reuse@correo.com');
        $payload = $this->payload('reuse@correo.com', $codigo);

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $payload)->assertOk();
        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', PasswordRecoveryService::INVALID_CODE_MESSAGE);
    }

    public function test_seven_digit_code_is_rejected(): void
    {
        $this->createUserWithEmail('len@correo.com');
        $this->requestCodeFor('len@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('len@correo.com', '1234567'))
            ->assertStatus(422);
    }

    public function test_alphabetic_code_is_rejected(): void
    {
        $this->createUserWithEmail('abc@correo.com');
        $this->requestCodeFor('abc@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('abc@correo.com', '12ab56'))
            ->assertStatus(422);
    }

    public function test_password_shorter_than_12_is_rejected(): void
    {
        $this->createUserWithEmail('short@correo.com');
        $codigo = $this->requestCodeFor('short@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', [
            'correo_electronico' => 'short@correo.com',
            'codigo' => $codigo,
            'contrasena' => 'Corta123',
            'contrasena_confirmation' => 'Corta123',
        ])->assertStatus(422)->assertJsonValidationErrors(['contrasena']);
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $this->createUserWithEmail('confirm@correo.com');
        $codigo = $this->requestCodeFor('confirm@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', [
            'correo_electronico' => 'confirm@correo.com',
            'codigo' => $codigo,
            'contrasena' => self::NEW_PASSWORD,
            'contrasena_confirmation' => 'OtraClaveSegura12',
        ])->assertStatus(422)->assertJsonValidationErrors(['contrasena']);
    }

    public function test_inactive_user_cannot_complete_reset_and_request_stays_generic(): void
    {
        $this->createUserWithEmail('inactivo@correo.com', activo: false);

        $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'inactivo@correo.com',
        ])
            ->assertOk()
            ->assertExactJson(['message' => PasswordRecoveryService::REQUEST_MESSAGE]);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_codes', 0);

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('inactivo@correo.com', '123456'))
            ->assertStatus(422);
    }

    public function test_email_is_normalized_before_lookup(): void
    {
        $this->createUserWithEmail('Ruth@Correo.COM');

        $codigo = $this->requestCodeFor('  ruth@correo.com ');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);
        $this->assertDatabaseCount('password_reset_codes', 1);
    }

    public function test_audit_does_not_contain_password_or_code(): void
    {
        $usuario = $this->createUserWithEmail('audit@correo.com');
        $codigo = $this->requestCodeFor('audit@correo.com');

        $this->postJson('/api/v1/recuperacion-contrasena/verificar', $this->payload('audit@correo.com', $codigo))
            ->assertOk();

        $audit = Auditoria::query()->where('accion', 'PASSWORD_RESET')->firstOrFail();
        $this->assertSame($usuario->id, $audit->usuario_id);
        $this->assertSame('usuarios', $audit->tabla_afectada);
        $blob = json_encode([$audit->datos_antes, $audit->datos_despues]);
        $this->assertIsString($blob);
        $this->assertStringNotContainsString($codigo, $blob);
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $blob);
        $this->assertStringNotContainsString('codigo_hash', $blob);
    }

    public function test_request_rate_limit_returns_generic_429(): void
    {
        $this->createUserWithEmail('limit@correo.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/recuperacion-contrasena', [
                'correo_electronico' => 'limit@correo.com',
            ])->assertOk();
        }

        $existing = $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'limit@correo.com',
        ]);
        $unknown = $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => 'otro-limit@correo.com',
        ]);

        $existing->assertStatus(429);
        $unknown->assertStatus(429);
        $this->assertSame($existing->json('message'), $unknown->json('message'));
        $this->assertSame(
            'Has realizado demasiadas solicitudes. Inténtalo nuevamente más tarde.',
            $existing->json('message'),
        );
    }

    public function test_verify_rate_limit_returns_429(): void
    {
        $this->createUserWithEmail('vlimit@correo.com');
        $this->requestCodeFor('vlimit@correo.com');

        $last = null;
        for ($i = 0; $i < 16; $i++) {
            $last = $this->postJson(
                '/api/v1/recuperacion-contrasena/verificar',
                $this->payload('vlimit@correo.com', '000000'),
            );
        }

        $this->assertNotNull($last);
        $last->assertStatus(429);
    }

    /** @return array<string, string> */
    private function payload(string $email, string $codigo): array
    {
        return [
            'correo_electronico' => $email,
            'codigo' => $codigo,
            'contrasena' => self::NEW_PASSWORD,
            'contrasena_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function createUserWithEmail(string $email, bool $activo = true): Usuario
    {
        $miembro = Miembro::factory()->create(['correo_electronico' => $email]);

        return Usuario::factory()->create([
            'miembro_id' => $miembro->id,
            'contrasena' => 'OldPassword1234',
            'activo' => $activo,
        ]);
    }

    private function requestCodeFor(string $email): string
    {
        $normalized = strtolower(trim($email));
        $usuario = Usuario::query()
            ->whereHas('miembro', function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(TRIM(correo_electronico)) = ?', [$normalized]);
            })
            ->firstOrFail();

        $response = $this->postJson('/api/v1/recuperacion-contrasena', [
            'correo_electronico' => $email,
        ]);
        $response->assertOk();

        $codigo = null;
        Notification::assertSentTo(
            $usuario,
            PasswordResetCodeNotification::class,
            function (PasswordResetCodeNotification $notification) use (&$codigo): bool {
                $codigo = $notification->codigo;

                return true;
            },
        );

        $this->assertNotNull($codigo);
        $this->assertStringNotContainsString($codigo, (string) $response->getContent());

        return $codigo;
    }
}
