<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Anuncio;
use App\Models\Curso;
use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class ComunicacionApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_me_includes_authenticated_member_iglesia_id(): void
    {
        $this->actingAsAdmin();
        $churchId = $this->principalChurchId();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.miembro.iglesia_id', $churchId);
    }

    public function test_admin_creates_edits_and_deletes_anuncio(): void
    {
        $admin = $this->actingAsAdmin();
        $churchId = $this->principalChurchId();

        $id = (int) $this->postJson('/api/v1/anuncios', [
            'iglesia_id' => $churchId,
            'titulo' => 'Aviso de prueba',
            'contenido' => 'Contenido institucional',
            'estado' => 'borrador',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('anuncios', ['id' => $id, 'estado' => 'borrador']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'CREATE', 'tabla_afectada' => 'anuncios', 'registro_id' => $id]);

        $this->putJson("/api/v1/anuncios/{$id}", [
            'titulo' => 'Aviso editado',
            'contenido' => 'Contenido actualizado',
            'estado' => 'borrador',
        ])->assertOk()->assertJsonPath('data.titulo', 'Aviso editado');

        $this->assertDatabaseHas('auditorias', ['accion' => 'UPDATE', 'tabla_afectada' => 'anuncios', 'registro_id' => $id]);

        $this->deleteJson("/api/v1/anuncios/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('anuncios', ['id' => $id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'DELETE', 'tabla_afectada' => 'anuncios', 'registro_id' => $id]);
    }

    public function test_admin_publishes_anuncio_and_notifies_church_users(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.ok');
        $docente = $this->createDocenteUser('docente.anun.ok');
        $ajeno = $this->createUserInOtherChurch('alumno.otra.iglesia');

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', $this->anuncioPayload('borrador'))
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseCount('notificaciones', 0);

        $this->putJson("/api/v1/anuncios/{$id}", ['estado' => 'publicado'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'publicado');

        $this->assertNotNull(Anuncio::query()->find($id)?->publicado_at);
        $this->assertDatabaseHas('auditorias', ['accion' => 'PUBLISH', 'tabla_afectada' => 'anuncios', 'registro_id' => $id]);

        $notificacion = Notificacion::query()->where('tipo', 'anuncio')->first();
        $this->assertNotNull($notificacion);
        $this->assertNotNull($notificacion->enviado_at);
        $this->assertDatabaseHas('auditorias', ['accion' => 'SEND', 'tabla_afectada' => 'notificaciones', 'registro_id' => $notificacion->id]);

        $destinatarios = NotificacionDestinatario::query()
            ->where('notificacion_id', $notificacion->id)
            ->pluck('usuario_id');

        $this->assertTrue($destinatarios->contains($admin->id));
        $this->assertTrue($destinatarios->contains($alumno->id));
        $this->assertTrue($destinatarios->contains($docente->id));
        $this->assertFalse($destinatarios->contains($ajeno->id));
    }

    public function test_publishing_twice_does_not_duplicate_notifications(): void
    {
        $admin = $this->actingAsAdmin();
        $this->createAlumnoUser('alumno.anun.dup');

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', $this->anuncioPayload('publicado'))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(1, Notificacion::query()->where('tipo', 'anuncio')->count());

        $this->putJson("/api/v1/anuncios/{$id}", [
            'titulo' => 'Sigue publicado',
            'estado' => 'publicado',
        ])->assertOk();

        $this->assertSame(1, Notificacion::query()->where('tipo', 'anuncio')->count());
    }

    public function test_updating_published_anuncio_recreates_missing_notification_once(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.recreate');
        $docente = $this->createDocenteUser('docente.anun.recreate');

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', $this->anuncioPayload('publicado'))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(1, Notificacion::query()->where('anuncio_id', $id)->count());
        app(\App\Services\NotificacionService::class)->deleteGeneratedByAnuncio($id);
        $this->assertSame(0, Notificacion::query()->where('anuncio_id', $id)->count());

        $this->putJson("/api/v1/anuncios/{$id}", [
            'titulo' => 'Sigue publicado',
            'estado' => 'publicado',
        ])->assertOk();

        $this->assertSame(1, Notificacion::query()->where('anuncio_id', $id)->count());
        $notificacion = Notificacion::query()->where('anuncio_id', $id)->firstOrFail();
        $destinatarios = NotificacionDestinatario::query()
            ->where('notificacion_id', $notificacion->id)
            ->pluck('usuario_id');
        $this->assertTrue($destinatarios->contains($admin->id));
        $this->assertTrue($destinatarios->contains($alumno->id));
        $this->assertTrue($destinatarios->contains($docente->id));

        $this->putJson("/api/v1/anuncios/{$id}", ['titulo' => 'Todavía uno'])->assertOk();
        $this->assertSame(1, Notificacion::query()->where('anuncio_id', $id)->count());
    }

    public function test_published_anuncio_persists_anuncio_id_on_its_notification(): void
    {
        $admin = $this->actingAsAdmin();
        $this->createAlumnoUser('alumno.anun.fk');

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', $this->anuncioPayload('publicado'))
            ->assertCreated()
            ->json('data.id');

        $notificacion = Notificacion::query()->where('tipo', 'anuncio')->first();
        $this->assertNotNull($notificacion);
        $this->assertSame($id, (int) $notificacion->anuncio_id);
    }

    public function test_deleting_anuncio_removes_only_its_generated_notifications(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.purge');
        $ajeno = $this->createUserInOtherChurch('alumno.anun.purge.x');

        Sanctum::actingAs($admin);
        $keepId = (int) $this->postJson('/api/v1/anuncios', array_merge(
            $this->anuncioPayload('publicado'),
            ['titulo' => 'Anuncio que permanece'],
        ))->assertCreated()->json('data.id');

        $deleteId = (int) $this->postJson('/api/v1/anuncios', array_merge(
            $this->anuncioPayload('publicado'),
            ['titulo' => 'Anuncio a eliminar'],
        ))->assertCreated()->json('data.id');

        $keepNotice = Notificacion::query()->where('anuncio_id', $keepId)->firstOrFail();
        $deleteNotice = Notificacion::query()->where('anuncio_id', $deleteId)->firstOrFail();
        $tareaNotice = $this->dispatchTo([$alumno->id], 'Nueva tarea publicada', 'tarea');

        Sanctum::actingAs($ajeno);
        $this->deleteJson("/api/v1/anuncios/{$deleteId}")->assertForbidden();
        $this->assertDatabaseHas('notificaciones', ['id' => $deleteNotice->id]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $deleteNotice->id,
        ]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/anuncios/{$deleteId}")->assertNoContent();

        $this->assertDatabaseMissing('anuncios', ['id' => $deleteId]);
        $this->assertDatabaseMissing('notificaciones', ['id' => $deleteNotice->id]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $deleteNotice->id,
        ]);
        $this->assertDatabaseHas('anuncios', ['id' => $keepId]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $keepNotice->id,
            'anuncio_id' => $keepId,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $tareaNotice->id,
            'tipo' => 'tarea',
        ]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $tareaNotice->id,
            'usuario_id' => $alumno->id,
        ]);

        Sanctum::actingAs($alumno);
        $inboxIds = collect($this->getJson('/api/v1/notificaciones/mis')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertTrue($inboxIds->contains($keepNotice->id));
        $this->assertTrue($inboxIds->contains($tareaNotice->id));
        $this->assertFalse($inboxIds->contains($deleteNotice->id));
    }

    public function test_expired_anuncio_notifications_are_purged_the_following_lima_midnight(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.exp');

        $this->travelTo(Carbon::parse('2026-09-14 10:00:00', 'America/Lima'));

        Sanctum::actingAs($admin);
        $keepId = (int) $this->postJson('/api/v1/anuncios', array_merge(
            $this->anuncioPayload('publicado'),
            [
                'titulo' => 'Sigue vigente',
                'vence_at' => '2026-09-20 23:59:59',
            ],
        ))->assertCreated()->json('data.id');

        $expireId = (int) $this->postJson('/api/v1/anuncios', array_merge(
            $this->anuncioPayload('publicado'),
            [
                'titulo' => 'Vence hoy',
                'vence_at' => '2026-09-14 23:59:59',
            ],
        ))->assertCreated()->json('data.id');

        $keepNotice = Notificacion::query()->where('anuncio_id', $keepId)->firstOrFail();
        $expireNotice = Notificacion::query()->where('anuncio_id', $expireId)->firstOrFail();
        $tareaNotice = $this->dispatchTo([$alumno->id], 'Nueva tarea publicada', 'tarea');
        $legacyNotice = $this->dispatchTo([$alumno->id], 'Aviso legado', 'anuncio');

        $this->artisan('notificaciones:purge-expired-anuncios')
            ->expectsOutput('Notificaciones de anuncios vencidos eliminadas: 0.')
            ->assertSuccessful();

        $this->assertDatabaseHas('notificaciones', ['id' => $expireNotice->id]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $expireNotice->id,
        ]);

        $this->travelTo(Carbon::parse('2026-09-15 00:00:00', 'America/Lima'));
        $this->artisan('notificaciones:purge-expired-anuncios')
            ->expectsOutput('Notificaciones de anuncios vencidos eliminadas: 1.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notificaciones', ['id' => $expireNotice->id]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $expireNotice->id,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $keepNotice->id,
            'anuncio_id' => $keepId,
            'tipo' => 'anuncio',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $tareaNotice->id,
            'tipo' => 'tarea',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $legacyNotice->id,
            'tipo' => 'anuncio',
            'anuncio_id' => null,
        ]);
        $this->assertDatabaseHas('anuncios', ['id' => $expireId]);
    }

    public function test_legacy_anuncio_purge_command_previews_then_deletes_only_null_anuncio_id(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.legacy');

        Sanctum::actingAs($admin);
        $linkedId = (int) $this->postJson('/api/v1/anuncios', array_merge(
            $this->anuncioPayload('publicado'),
            ['titulo' => 'Anuncio con fk'],
        ))->assertCreated()->json('data.id');

        $linkedNotice = Notificacion::query()->where('anuncio_id', $linkedId)->firstOrFail();
        $legacyNotice = $this->dispatchTo([$alumno->id], 'Aviso legado', 'anuncio');
        $tareaNotice = $this->dispatchTo([$alumno->id], 'Nueva tarea publicada', 'tarea');
        $examenNotice = $this->dispatchTo([$alumno->id], 'Nota de examen registrada', 'examen');

        $this->assertNull($legacyNotice->anuncio_id);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $legacyNotice->id,
            'usuario_id' => $alumno->id,
        ]);

        $this->artisan('notificaciones:purge-legacy-anuncios')
            ->expectsOutput('Notificaciones legacy (tipo=anuncio AND anuncio_id IS NULL): 1')
            ->expectsOutput('Nada fue eliminado. Ejecute con --force para borrar únicamente estas filas y sus destinatarios.')
            ->assertSuccessful();

        $this->assertDatabaseHas('notificaciones', ['id' => $legacyNotice->id, 'anuncio_id' => null]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $legacyNotice->id,
        ]);

        $this->artisan('notificaciones:purge-legacy-anuncios', ['--force' => true])
            ->expectsOutput('Notificaciones eliminadas: 1')
            ->expectsOutput('Destinatarios eliminados: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notificaciones', ['id' => $legacyNotice->id]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $legacyNotice->id,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $linkedNotice->id,
            'anuncio_id' => $linkedId,
            'tipo' => 'anuncio',
        ]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $linkedNotice->id,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $tareaNotice->id,
            'tipo' => 'tarea',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $examenNotice->id,
            'tipo' => 'examen',
        ]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $tareaNotice->id,
            'usuario_id' => $alumno->id,
        ]);
    }

    public function test_anuncio_vigencia_null_vence_at_is_open(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        $anuncio = Anuncio::query()->create($this->anuncioAttributes(
            $admin,
            'publicado',
            Carbon::parse('2026-09-16 08:00:00', 'America/Lima'),
            null,
        ));

        $this->assertTrue($anuncio->fresh()->isVigente());
        $this->assertTrue(Anuncio::query()->vigente()->whereKey($anuncio->id)->exists());
    }

    public function test_anuncio_vigencia_end_of_lima_day_stays_open_until_next_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Vigente todo el 16',
            'publicado_at' => '2026-09-16 08:00:00',
            'vence_at' => '2026-09-16 23:59:59',
        ]))->assertCreated()->json('data.id');

        $this->createDocenteUser('docente.vig.eod');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        $this->travelTo(Carbon::parse('2026-09-16 23:59:00', 'America/Lima'));
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        $this->travelTo(Carbon::parse('2026-09-17 00:00:00', 'America/Lima'));
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($id));
        $this->assertFalse(Anuncio::query()->findOrFail($id)->isVigente());
    }

    public function test_anuncio_vigencia_date_only_vence_at_is_end_of_lima_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Vence fecha calendario',
            'vence_at' => '2026-09-16',
        ]))->assertCreated()->json('data.id');

        $this->createDocenteUser('docente.vig.date');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        $this->travelTo(Carbon::parse('2026-09-17 00:00:00', 'America/Lima'));
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($id));
    }

    public function test_anuncio_vigencia_naive_midnight_vence_at_is_end_of_lima_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Vence medianoche naive',
            'vence_at' => '2026-09-16 00:00:00',
        ]))->assertCreated()->json('data.id');

        $this->assertSame(
            '2026-09-16',
            $this->getJson("/api/v1/anuncios/{$id}")->assertOk()->json('data.vence_on'),
        );

        $this->createDocenteUser('docente.vig.midnight');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        $this->travelTo(Carbon::parse('2026-09-17 00:00:00', 'America/Lima'));
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($id));
    }

    public function test_legacy_utc_midnight_vence_at_stays_open_during_lima_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        $anuncio = Anuncio::query()->create($this->anuncioAttributes(
            $admin,
            'publicado',
            '2026-09-16 08:00:00',
            '2026-09-16 00:00:00',
        ));

        $this->assertTrue($anuncio->fresh()->isVigente());
        $this->assertTrue(Anuncio::query()->vigente()->whereKey($anuncio->id)->exists());

        $this->createDocenteUser('docente.vig.legacy');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($anuncio->id));
    }

    public function test_create_without_client_timestamps_is_visible_to_docente_and_alumno(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:16:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();
        $docente = $this->createDocenteUser('docente.anun.now');
        $alumno = $this->createAlumnoUser('alumno.anun.now');

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', $this->anuncioPayload('publicado'))
            ->assertCreated()
            ->json('data.id');

        $anuncio = Anuncio::query()->findOrFail($id);
        $this->assertSame('publicado', $anuncio->estado);
        $this->assertNotNull($anuncio->publicado_at);
        $this->assertNull($anuncio->vence_at);
        $this->assertTrue($anuncio->isVigente());

        Sanctum::actingAs($docente);
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));

        Sanctum::actingAs($alumno);
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));
    }

    public function test_update_rewrites_naive_midnight_vence_at_to_lima_end_of_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Para editar vigencia',
            'vence_at' => '2026-09-16 00:00:00',
        ]))->assertCreated()->json('data.id');

        $this->putJson("/api/v1/anuncios/{$id}", [
            'titulo' => 'Vigencia normalizada',
            'vence_at' => '2026-09-16',
        ])->assertOk();

        $anuncio = Anuncio::query()->findOrFail($id);
        $this->assertTrue(
            Carbon::parse('2026-09-16 23:59:59', 'America/Lima')->utc()->equalTo($anuncio->vence_at),
        );

        $this->createDocenteUser('docente.vig.update');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));
    }

    public function test_anuncio_vigencia_expired_yesterday_is_closed(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        $anuncio = Anuncio::query()->create($this->anuncioAttributes(
            $admin,
            'publicado',
            Carbon::parse('2026-09-14 10:00:00', 'America/Lima'),
            Carbon::parse('2026-09-15 23:59:59', 'America/Lima'),
        ));

        $this->assertFalse($anuncio->fresh()->isVigente());
        $this->createDocenteUser('docente.vig.ayer');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($anuncio->id));
    }

    public function test_anuncio_vigencia_future_publicado_at_is_closed(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Aún no publicado',
            'publicado_at' => '2026-09-17 08:00:00',
            'vence_at' => '2026-09-20 23:59:59',
        ]))->assertCreated()->json('data.id');

        $this->assertFalse(Anuncio::query()->findOrFail($id)->isVigente());
        $this->createDocenteUser('docente.vig.futuro');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($id));
    }

    public function test_published_vigente_anuncio_of_same_church_appears_in_publicados(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Aviso de la iglesia',
            'publicado_at' => '2026-09-16 08:00:00',
            'vence_at' => '2026-09-16 23:59:59',
        ]))->assertCreated()->json('data.id');

        $this->createAlumnoUser('alumno.vig.ok');
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));
    }

    public function test_anuncio_from_other_church_does_not_appear_in_publicados(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 11:30:00', 'America/Lima'));
        $admin = $this->actingAsAdmin();

        Sanctum::actingAs($admin);
        $id = (int) $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'titulo' => 'Solo iglesia principal',
            'publicado_at' => '2026-09-16 08:00:00',
            'vence_at' => '2026-09-16 23:59:59',
        ]))->assertCreated()->json('data.id');

        $ajeno = $this->createUserInOtherChurch('alumno.vig.ajeno');
        Sanctum::actingAs($ajeno);
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($id));
    }

    public function test_invalid_anuncio_estado_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/anuncios', $this->anuncioPayload('urgente'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    public function test_alumno_and_docente_can_read_published_but_cannot_mutate(): void
    {
        $admin = $this->actingAsAdmin();
        $vigente = Anuncio::query()->create($this->anuncioAttributes($admin, 'publicado', now()));
        $borrador = Anuncio::query()->create($this->anuncioAttributes($admin, 'borrador'));
        $vencido = Anuncio::query()->create($this->anuncioAttributes(
            $admin,
            'publicado',
            now()->subDays(3),
            now()->subDay(),
        ));

        foreach (['alumno.anun.read' => 'alumno', 'docente.anun.read' => 'docente'] as $username => $kind) {
            $user = $kind === 'alumno'
                ? $this->createAlumnoUser($username)
                : $this->createDocenteUser($username);

            Sanctum::actingAs($user);
            $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
            $this->assertTrue($ids->contains($vigente->id), $kind);
            $this->assertFalse($ids->contains($borrador->id), $kind);
            $this->assertFalse($ids->contains($vencido->id), $kind);

            $this->getJson("/api/v1/anuncios/{$vigente->id}")->assertOk()->assertJsonPath('data.titulo', $vigente->titulo);
            $this->getJson('/api/v1/anuncios')->assertForbidden();
            $this->postJson('/api/v1/anuncios', $this->anuncioPayload('publicado'))->assertForbidden();
            $this->putJson("/api/v1/anuncios/{$vigente->id}", ['titulo' => 'Hack'])->assertForbidden();
            $this->deleteJson("/api/v1/anuncios/{$vigente->id}")->assertForbidden();
        }
    }

    public function test_user_from_other_church_cannot_see_anuncios(): void
    {
        $admin = $this->actingAsAdmin();
        $anuncio = Anuncio::query()->create($this->anuncioAttributes($admin, 'publicado', now()));
        $ajeno = $this->createUserInOtherChurch('alumno.anun.ajeno');

        Sanctum::actingAs($ajeno);
        $ids = collect($this->getJson('/api/v1/anuncios/publicados')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($anuncio->id));
        $this->getJson("/api/v1/anuncios/{$anuncio->id}")->assertForbidden();
    }

    public function test_client_cannot_choose_anuncio_recipients(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.anun.ids');
        $ajeno = $this->createUserInOtherChurch('alumno.anun.ids.x');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/anuncios', array_merge($this->anuncioPayload('publicado'), [
            'usuario_ids' => [$ajeno->id],
        ]))->assertCreated();

        $notificacion = Notificacion::query()->where('tipo', 'anuncio')->firstOrFail();
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $alumno->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $ajeno->id,
        ]);
    }

    public function test_inbox_count_and_mark_read_are_scoped_to_the_user(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.inbox.ok');
        $otro = $this->createAlumnoUser('alumno.inbox.otro');

        Sanctum::actingAs($admin);
        $notificacion = $this->dispatchTo([$alumno->id, $otro->id], 'Aviso propio');

        Sanctum::actingAs($alumno);
        $this->getJson('/api/v1/notificaciones')->assertForbidden();
        $inbox = $this->getJson('/api/v1/notificaciones/mis')->assertOk();
        $this->assertCount(1, $inbox->json('data'));
        $this->assertSame($notificacion->id, $inbox->json('data.0.id'));
        $this->assertArrayNotHasKey('destinatarios', $inbox->json('data.0'));
        $this->getJson('/api/v1/notificaciones/no-leidas/count')
            ->assertOk()
            ->assertExactJson(['count' => 1]);

        Sanctum::actingAs($otro);
        $this->postJson("/api/v1/notificaciones/{$notificacion->id}/leida")->assertOk();

        Sanctum::actingAs($alumno);
        $this->getJson('/api/v1/notificaciones/no-leidas/count')->assertExactJson(['count' => 1]);

        $ajena = $this->dispatchTo([$otro->id], 'Solo el otro');
        Sanctum::actingAs($alumno);
        $this->postJson("/api/v1/notificaciones/{$ajena->id}/leida")->assertForbidden();

        $this->postJson('/api/v1/notificaciones/marcar-todas-leidas')
            ->assertOk()
            ->assertJsonPath('actualizados', 1);

        $this->getJson('/api/v1/notificaciones/no-leidas/count')->assertExactJson(['count' => 0]);
        $this->assertNotNull(
            NotificacionDestinatario::query()
                ->where('notificacion_id', $notificacion->id)
                ->where('usuario_id', $alumno->id)
                ->value('leido_at'),
        );

        Sanctum::actingAs($otro);
        $this->getJson('/api/v1/notificaciones/no-leidas/count')->assertExactJson(['count' => 1]);
    }

    public function test_admin_keeps_administrative_notification_catalog(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/v1/notificaciones')->assertOk();
    }

    public function test_exam_notifications_still_reach_inbox(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.exam.inbox');

        Sanctum::actingAs($admin);
        $notificacion = $this->dispatchTo([$alumno->id], 'Nota de examen registrada', 'examen');

        Sanctum::actingAs($alumno);
        $this->getJson('/api/v1/notificaciones/mis')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notificacion->id)
            ->assertJsonPath('data.0.tipo', 'examen');
    }

    public function test_published_tarea_notifies_enrolled_student_not_foreign_church(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('COM-TAR');
        $alumno = $this->createAlumnoUser('alumno.tar.noti');
        $this->enrollAlumno($alumno, $programacion);
        $ajeno = $this->createUserInOtherChurch('alumno.tar.ajeno');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/tareas', [
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Ensayo semanal',
            'descripcion' => 'Entregar resumen',
            'publicado_at' => now()->toDateTimeString(),
            'fecha_limite_at' => now()->addDays(7)->toDateTimeString(),
            'puntaje_maximo' => 20,
        ])->assertCreated();

        $notificacion = Notificacion::query()->where('tipo', 'tarea')->first();
        $this->assertNotNull($notificacion);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $alumno->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $ajeno->id,
        ]);
    }

    public function test_entrega_notifies_assigned_teacher_and_grade_notifies_student(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('COM-ENT');
        $tarea = Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Tarea notificada',
            'descripcion' => 'Desc',
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 20,
            'creado_por_usuario_id' => $admin->id,
        ]);
        $alumno = $this->createAlumnoUser('alumno.ent.noti');
        $this->enrollAlumno($alumno, $programacion);
        $docente = $this->createDocenteUser('docente.ent.noti');
        $this->assignDocente($docente, $programacion);

        Sanctum::actingAs($alumno);
        $entregaId = (int) $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Mi entrega',
        ])->assertCreated()->json('data.id');

        $entregaNotice = Notificacion::query()
            ->where('tipo', 'tarea')
            ->where('titulo', 'Nueva entrega de tarea')
            ->first();
        $this->assertNotNull($entregaNotice);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $entregaNotice->id,
            'usuario_id' => $docente->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $entregaNotice->id,
            'usuario_id' => $alumno->id,
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/entregas-tarea/{$entregaId}/calificar", [
            'nota' => 16,
        ])->assertOk();

        $notaNotice = Notificacion::query()
            ->where('tipo', 'tarea')
            ->where('titulo', 'Tarea calificada')
            ->first();
        $this->assertNotNull($notaNotice);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notaNotice->id,
            'usuario_id' => $alumno->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $notaNotice->id,
            'usuario_id' => $docente->id,
        ]);
    }

    public function test_forbidden_messages_are_in_spanish(): void
    {
        $this->createAlumnoUser('alumno.anun.msg');
        $message = $this->getJson('/api/v1/anuncios')->assertForbidden()->json('message');
        $this->assertIsString($message);
        $this->assertStringNotContainsString('This action is unauthorized.', $message);
    }

    /** @param  list<int>  $usuarioIds */
    private function dispatchTo(array $usuarioIds, string $titulo, string $tipo = 'general'): Notificacion
    {
        $admin = Usuario::query()->where('nombre_usuario', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        $id = (int) $this->postJson('/api/v1/notificaciones', [
            'iglesia_id' => $this->principalChurchId(),
            'titulo' => $titulo,
            'contenido' => 'Cuerpo',
            'tipo' => $tipo,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/notificaciones/{$id}/enviar", [
            'usuario_ids' => $usuarioIds,
        ])->assertOk();

        return Notificacion::query()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function anuncioPayload(string $estado): array
    {
        return [
            'iglesia_id' => $this->principalChurchId(),
            'titulo' => 'Comunicado',
            'contenido' => 'Texto del anuncio',
            'estado' => $estado,
        ];
    }

    /** @return array<string, mixed> */
    private function anuncioAttributes(
        Usuario $actor,
        string $estado,
        mixed $publicadoAt = null,
        mixed $venceAt = null,
    ): array {
        return [
            'iglesia_id' => $this->principalChurchId(),
            'titulo' => 'Anuncio '.$estado,
            'contenido' => 'Cuerpo',
            'estado' => $estado,
            'publicado_at' => $publicadoAt,
            'vence_at' => $venceAt,
            'creado_por_usuario_id' => $actor->id,
        ];
    }

    private function createProgramacion(string $codigo): ProgramacionAcademica
    {
        $curso = Curso::query()->create([
            'iglesia_id' => $this->principalChurchId(),
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);

        return ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => 'A',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }

    private function createUserInOtherChurch(string $username): Usuario
    {
        $now = now();
        $iglesiaId = DB::table('iglesias')->insertGetId([
            'codigo' => 'MMM-'.$username,
            'nombre' => 'Iglesia '.$username,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $miembroId = DB::table('miembros')->insertGetId([
            'iglesia_id' => $iglesiaId,
            'tipo_documento' => 'DNI',
            'numero_documento' => substr(md5($username), 0, 8),
            'nombres' => 'Ajeno',
            'apellidos' => $username,
            'fecha_nacimiento' => '2001-01-01',
            'sexo' => 'M',
            'correo_electronico' => $username.'@otra.local',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $usuarioId = DB::table('usuarios')->insertGetId([
            'miembro_id' => $miembroId,
            'nombre_usuario' => $username,
            'contrasena' => Hash::make('Admin123*'),
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Usuario::query()->findOrFail($usuarioId);
    }

    private function principalChurchId(): int
    {
        return (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
    }
}
