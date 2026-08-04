<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OperacionApiTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = Usuario::query()->where('nombre_usuario', 'admin')->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    public function test_lists_operational_resources(): void
    {
        $this->getJson('/api/v1/cultos')->assertOk();
        $this->getJson('/api/v1/bloques-culto')->assertOk();
        $this->getJson('/api/v1/participaciones-culto')->assertOk();
        $this->getJson('/api/v1/eventos')->assertOk();
        $this->getJson('/api/v1/anuncios')->assertOk();
        $this->getJson('/api/v1/notificaciones')->assertOk();
    }

    public function test_creates_culto_and_nested_bloque(): void
    {
        $churchId = (int) DB::table('iglesias')->value('id');
        $tipoCultoId = (int) DB::table('tipos_culto')->value('id');
        $tipoParticipacionId = (int) DB::table('tipos_participacion')->value('id');

        $cultoResponse = $this->postJson('/api/v1/cultos', [
            'iglesia_id' => $churchId,
            'tipo_culto_id' => $tipoCultoId,
            'inicio_at' => '2026-08-10 09:00:00',
            'fin_at' => '2026-08-10 11:00:00',
            'lugar' => 'Templo principal',
            'estado' => 'programado',
        ]);

        $cultoResponse->assertCreated();
        $cultoId = (int) $cultoResponse->json('data.id');

        $this->postJson('/api/v1/bloques-culto', [
            'culto_id' => $cultoId,
            'tipo_participacion_id' => $tipoParticipacionId,
            'orden' => 1,
            'contenido' => 'Apertura',
        ])->assertCreated();
    }

    public function test_rejects_overlapping_participacion_for_same_member(): void
    {
        $churchId = (int) DB::table('iglesias')->value('id');
        $tipoCultoId = (int) DB::table('tipos_culto')->value('id');
        $tipoParticipacionId = (int) DB::table('tipos_participacion')->value('id');
        $miembroId = (int) DB::table('miembros')->value('id');

        $firstCultoId = (int) $this->postJson('/api/v1/cultos', [
            'iglesia_id' => $churchId,
            'tipo_culto_id' => $tipoCultoId,
            'inicio_at' => '2026-08-11 09:00:00',
            'fin_at' => '2026-08-11 11:00:00',
            'estado' => 'programado',
        ])->json('data.id');

        $firstBloqueId = (int) $this->postJson('/api/v1/bloques-culto', [
            'culto_id' => $firstCultoId,
            'tipo_participacion_id' => $tipoParticipacionId,
            'orden' => 1,
        ])->json('data.id');

        $this->postJson('/api/v1/participaciones-culto', [
            'bloque_culto_id' => $firstBloqueId,
            'miembro_id' => $miembroId,
            'estado' => 'confirmado',
        ])->assertCreated();

        $secondCultoId = (int) $this->postJson('/api/v1/cultos', [
            'iglesia_id' => $churchId,
            'tipo_culto_id' => $tipoCultoId,
            'inicio_at' => '2026-08-11 10:00:00',
            'fin_at' => '2026-08-11 12:00:00',
            'estado' => 'programado',
        ])->json('data.id');

        $secondBloqueId = (int) $this->postJson('/api/v1/bloques-culto', [
            'culto_id' => $secondCultoId,
            'tipo_participacion_id' => $tipoParticipacionId,
            'orden' => 1,
        ])->json('data.id');

        $this->postJson('/api/v1/participaciones-culto', [
            'bloque_culto_id' => $secondBloqueId,
            'miembro_id' => $miembroId,
            'estado' => 'confirmado',
        ])->assertUnprocessable();
    }

    public function test_sends_and_marks_notification_as_read(): void
    {
        $churchId = (int) DB::table('iglesias')->value('id');

        $notificacionId = (int) $this->postJson('/api/v1/notificaciones', [
            'iglesia_id' => $churchId,
            'titulo' => 'Aviso de prueba',
            'contenido' => 'Contenido de prueba',
            'tipo' => 'general',
        ])->json('data.id');

        $this->postJson("/api/v1/notificaciones/{$notificacionId}/enviar", [
            'usuario_ids' => [$this->admin->id],
        ])->assertOk()->assertJsonPath('data.enviado_at', fn ($value) => $value !== null);

        $this->postJson("/api/v1/notificaciones/{$notificacionId}/leida")
            ->assertOk()
            ->assertJsonPath('data.leido_at', fn ($value) => $value !== null);
    }

    public function test_reports_endpoints_are_available(): void
    {
        $this->getJson('/api/v1/reportes/academicos')
            ->assertOk()
            ->assertJsonStructure(['data' => ['matriculas', 'calificaciones']]);

        $this->getJson('/api/v1/reportes/administrativos')
            ->assertOk()
            ->assertJsonStructure(['data' => ['iglesias', 'miembros']]);

        $this->getJson('/api/v1/reportes/certificados')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
