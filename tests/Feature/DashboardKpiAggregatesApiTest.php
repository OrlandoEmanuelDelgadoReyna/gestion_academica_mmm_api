<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Iglesia;
use App\Models\Matricula;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DashboardKpiAggregatesApiTest extends TestCase
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

    public function test_administrativos_usuarios_and_cursos_match_database_aggregates(): void
    {
        Usuario::factory()->create(['activo' => false]);
        Curso::query()->create([
            'iglesia_id' => (int) Iglesia::query()->value('id'),
            'codigo' => 'KPI-OFF',
            'nombre' => 'Curso inactivo KPI',
            'activo' => false,
        ]);

        $usuariosTotal = Usuario::query()->count();
        $usuariosActivos = Usuario::query()->where('activo', true)->count();
        $cursosTotal = Curso::query()->count();
        $cursosActivos = Curso::query()->where('activo', true)->count();

        $this->assertGreaterThan($usuariosActivos, $usuariosTotal);
        $this->assertGreaterThan($cursosActivos, $cursosTotal);

        $this->getJson('/api/v1/reportes/administrativos')
            ->assertOk()
            ->assertJsonPath('data.usuarios.total', $usuariosTotal)
            ->assertJsonPath('data.usuarios.activos', $usuariosActivos)
            ->assertJsonPath('data.usuarios.inactivos', $usuariosTotal - $usuariosActivos)
            ->assertJsonPath('data.cursos.total', $cursosTotal)
            ->assertJsonPath('data.cursos.activos', $cursosActivos)
            ->assertJsonPath('data.cursos.inactivos', $cursosTotal - $cursosActivos);
    }

    public function test_academicos_certificados_use_matriculas_as_universe(): void
    {
        $emitidos = Certificado::query()
            ->where('estado', Certificado::ESTADO_EMITIDO)
            ->whereNotNull('programacion_academica_id')
            ->count();
        $universo = Matricula::query()->count();

        $this->getJson('/api/v1/reportes/academicos')
            ->assertOk()
            ->assertJsonPath('data.certificados.emitidos', $emitidos)
            ->assertJsonPath('data.certificados.universo', $universo)
            ->assertJsonPath('data.matriculas.total', $universo);
    }
}
