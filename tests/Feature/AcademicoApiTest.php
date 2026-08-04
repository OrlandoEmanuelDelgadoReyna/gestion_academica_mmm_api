<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Usuario;
use Database\Seeders\InstitutionalCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AcademicoApiTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InstitutionalCatalogSeeder::class);
        $this->admin = Usuario::query()->where('nombre_usuario', 'admin')->firstOrFail();
    }

    public function test_admin_can_list_cursos(): void
    {
        Sanctum::actingAs($this->admin);

        Curso::query()->create([
            'iglesia_id' => $this->admin->miembro->iglesia_id,
            'codigo' => 'BIB-101',
            'nombre' => 'Fundamentos bíblicos',
            'descripcion' => 'Curso introductorio',
            'activo' => true,
        ]);

        $response = $this->getJson('/api/v1/cursos');

        $response->assertOk()
            ->assertJsonPath('data.0.codigo', 'BIB-101');
    }

    public function test_admin_can_create_curso(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/cursos', [
            'iglesia_id' => $this->admin->miembro->iglesia_id,
            'codigo' => 'BIB-102',
            'nombre' => 'Doctrina cristiana',
            'descripcion' => 'Curso avanzado',
            'activo' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.codigo', 'BIB-102');

        $this->assertDatabaseHas('cursos', ['codigo' => 'BIB-102']);
    }

    public function test_admin_can_show_curso(): void
    {
        Sanctum::actingAs($this->admin);

        $curso = Curso::query()->create([
            'iglesia_id' => $this->admin->miembro->iglesia_id,
            'codigo' => 'BIB-201',
            'nombre' => 'Hermenéutica',
            'activo' => true,
        ]);

        $response = $this->getJson("/api/v1/cursos/{$curso->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $curso->id)
            ->assertJsonPath('data.nombre', 'Hermenéutica');
    }

    public function test_admin_can_update_curso(): void
    {
        Sanctum::actingAs($this->admin);

        $curso = Curso::query()->create([
            'iglesia_id' => $this->admin->miembro->iglesia_id,
            'codigo' => 'BIB-301',
            'nombre' => 'Nombre original',
            'activo' => true,
        ]);

        $response = $this->putJson("/api/v1/cursos/{$curso->id}", [
            'nombre' => 'Nombre actualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado');

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nombre' => 'Nombre actualizado']);
    }
}
