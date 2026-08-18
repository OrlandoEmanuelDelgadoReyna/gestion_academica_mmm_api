<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ProgramacionAcademica;
use App\Models\Usuario;
use Database\Seeders\InstitutionalCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

trait AuthenticatesApiUsers
{
    protected function seedInstitutionalCatalog(): void
    {
        $this->seed(InstitutionalCatalogSeeder::class);
    }

    protected function actingAsAdmin(): Usuario
    {
        $usuario = Usuario::query()->where('nombre_usuario', 'admin')->firstOrFail();

        Sanctum::actingAs($usuario);

        return $usuario;
    }

    /** Creates a DOCENTE user without academico.gestionar. */
    protected function createAcademicUser(string $username = 'docente'): Usuario
    {
        return $this->createDocenteUser($username);
    }

    protected function createDocenteUser(string $username = 'docente'): Usuario
    {
        $this->seedInstitutionalCatalog();

        $church = \DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $adminId = Usuario::query()->where('nombre_usuario', 'admin')->value('id');
        $docenteRole = \DB::table('roles')->where('codigo', 'DOCENTE')->value('id');
        $now = now();
        $documento = $this->docenteDocumentoFor($username);

        \DB::table('miembros')->updateOrInsert(
            ['iglesia_id' => $church, 'tipo_documento' => 'DNI', 'numero_documento' => $documento],
            [
                'nombres' => 'Docente',
                'apellidos' => $username,
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'M',
                'correo_electronico' => $username.'@mmm.local',
                'telefono' => '999999998',
                'direccion' => 'Dirección docente',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $memberId = \DB::table('miembros')->where('numero_documento', $documento)->value('id');

        \DB::table('usuarios')->updateOrInsert(
            ['nombre_usuario' => $username],
            ['miembro_id' => $memberId, 'contrasena' => Hash::make('Admin123*'), 'activo' => true, 'updated_at' => $now, 'created_at' => $now],
        );

        $usuarioId = Usuario::query()->where('nombre_usuario', $username)->value('id');

        \DB::table('usuario_roles')->updateOrInsert(
            ['usuario_id' => $usuarioId, 'rol_id' => $docenteRole],
            ['asignado_por_usuario_id' => $adminId, 'asignado_at' => $now, 'updated_at' => $now, 'created_at' => $now],
        );

        $usuario = Usuario::query()->findOrFail($usuarioId);
        Sanctum::actingAs($usuario);

        return $usuario;
    }

    protected function createDocenteUserWithoutMiembro(string $username = 'docente.sinmiembro'): Usuario
    {
        $usuario = $this->createDocenteUser($username);
        $usuario->miembro_id = null;
        Sanctum::actingAs($usuario);

        return $usuario;
    }

    protected function assignDocente(Usuario $usuario, ProgramacionAcademica $programacion): void
    {
        $programacion->docentes()->syncWithoutDetaching([(int) $usuario->miembro_id]);
    }

    protected function actingAsCertificador(): Usuario
    {
        $this->seedInstitutionalCatalog();
        $usuario = $this->actingAsAdmin();

        return $usuario;
    }

    private function docenteDocumentoFor(string $username): string
    {
        if ($username === 'docente') {
            return 'DOCENTE01';
        }

        return 'D'.str_pad((string) (abs(crc32($username)) % 10000000), 7, '0', STR_PAD_LEFT);
    }
}
