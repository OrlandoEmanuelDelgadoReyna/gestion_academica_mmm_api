<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Iglesia;
use App\Models\Miembro;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class DashboardKpiAggregatesApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = Usuario::query()->where('nombre_usuario', 'admin')->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    public function test_administrativos_includes_usuarios_and_cursos_aggregates_from_database(): void
    {
        Usuario::factory()->create(['activo' => false]);
        Curso::query()->create([
            'iglesia_id' => (int) Iglesia::query()->value('id'),
            'codigo' => 'KPI-OFF',
            'nombre' => 'Curso inactivo KPI',
            'activo' => false,
        ]);

        $iglesiasTotal = Iglesia::query()->count();
        $iglesiasActivas = Iglesia::query()->where('activo', true)->count();
        $miembrosTotal = Miembro::query()->count();
        $usuariosTotal = Usuario::query()->count();
        $usuariosActivos = Usuario::query()->where('activo', true)->count();
        $usuariosInactivos = Usuario::query()->where('activo', false)->count();
        $cursosTotal = Curso::query()->count();
        $cursosActivos = Curso::query()->where('activo', true)->count();
        $cursosInactivos = Curso::query()->where('activo', false)->count();

        $this->assertSame($usuariosTotal, $usuariosActivos + $usuariosInactivos);
        $this->assertSame($cursosTotal, $cursosActivos + $cursosInactivos);
        $this->assertGreaterThan(0, $usuariosInactivos);
        $this->assertGreaterThan(0, $cursosInactivos);

        $this->getJson('/api/v1/reportes/administrativos')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'iglesias' => ['total', 'activas'],
                'miembros' => ['total'],
                'usuarios' => ['total', 'activos', 'inactivos'],
                'cursos' => ['total', 'activos', 'inactivos'],
            ]])
            ->assertJsonPath('data.iglesias.total', $iglesiasTotal)
            ->assertJsonPath('data.iglesias.activas', $iglesiasActivas)
            ->assertJsonPath('data.miembros.total', $miembrosTotal)
            ->assertJsonPath('data.usuarios.total', $usuariosTotal)
            ->assertJsonPath('data.usuarios.activos', $usuariosActivos)
            ->assertJsonPath('data.usuarios.inactivos', $usuariosInactivos)
            ->assertJsonPath('data.cursos.total', $cursosTotal)
            ->assertJsonPath('data.cursos.activos', $cursosActivos)
            ->assertJsonPath('data.cursos.inactivos', $cursosInactivos);
    }

    public function test_administrativos_keeps_global_scope_like_iglesias_and_miembros(): void
    {
        $otherChurch = Iglesia::factory()->create(['activo' => true]);
        Curso::query()->create([
            'iglesia_id' => $otherChurch->id,
            'codigo' => 'KPI-OTRA',
            'nombre' => 'Curso de otra iglesia',
            'activo' => true,
        ]);
        Usuario::factory()->create(['activo' => true]);

        $response = $this->getJson('/api/v1/reportes/administrativos')->assertOk();

        $this->assertSame(Iglesia::query()->count(), $response->json('data.iglesias.total'));
        $this->assertSame(Miembro::query()->count(), $response->json('data.miembros.total'));
        $this->assertSame(Usuario::query()->count(), $response->json('data.usuarios.total'));
        $this->assertSame(Curso::query()->count(), $response->json('data.cursos.total'));
        $this->assertSame(
            (int) $response->json('data.usuarios.activos') + (int) $response->json('data.usuarios.inactivos'),
            (int) $response->json('data.usuarios.total'),
        );
        $this->assertSame(
            (int) $response->json('data.cursos.activos') + (int) $response->json('data.cursos.inactivos'),
            (int) $response->json('data.cursos.total'),
        );
    }

    public function test_administrativos_rejects_user_without_auditoria_permission(): void
    {
        $this->createDocenteUser('docente.kpi');

        $this->getJson('/api/v1/reportes/administrativos')->assertForbidden();
    }
}
