<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Seeds deterministic institutional catalogues and a usable administrator account. */
class InstitutionalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $churchId = DB::table('iglesias')->updateOrInsert(['codigo' => 'MMM-PRINCIPAL'], ['nombre' => 'Iglesia MMM Principal', 'direccion' => 'Dirección institucional', 'telefono' => '999999999', 'correo_electronico' => 'contacto@mmm.local', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            $church = DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

            foreach ([['codigo' => 'ADMINISTRADOR', 'nombre' => 'Administrador', 'descripcion' => 'Administración integral del sistema'], ['codigo' => 'SECRETARIA', 'nombre' => 'Secretaría', 'descripcion' => 'Gestión de miembros y operación'], ['codigo' => 'DOCENTE', 'nombre' => 'Docente', 'descripcion' => 'Gestión académica']] as $role) {
                DB::table('roles')->updateOrInsert(['codigo' => $role['codigo']], $role + ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }

            $permissions = ['usuarios.ver', 'usuarios.crear', 'usuarios.actualizar', 'usuarios.eliminar', 'iglesias.ver', 'iglesias.crear', 'iglesias.actualizar', 'miembros.ver', 'miembros.gestionar', 'academico.gestionar', 'cultos.gestionar', 'certificados.emitir', 'auditoria.ver'];
            foreach ($permissions as $code) {
                [$module] = explode('.', $code);
                DB::table('permisos')->updateOrInsert(['codigo' => $code], ['modulo' => $module, 'nombre' => str_replace('.', ' ', $code), 'descripcion' => 'Permiso institucional '.$code, 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }

            $adminRole = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');
            foreach (DB::table('permisos')->pluck('id') as $permissionId) {
                DB::table('rol_permisos')->updateOrInsert(['rol_id' => $adminRole, 'permiso_id' => $permissionId], ['asignado_at' => $now, 'updated_at' => $now, 'created_at' => $now]);
            }

            foreach ([['codigo' => 'VISITANTE', 'nombre' => 'Visitante', 'orden' => 1], ['codigo' => 'CONGREGANTE', 'nombre' => 'Congregante', 'orden' => 2], ['codigo' => 'MIEMBRO', 'nombre' => 'Miembro', 'orden' => 3], ['codigo' => 'INACTIVO', 'nombre' => 'Inactivo', 'orden' => 4]] as $state) {
                DB::table('estados_membresia')->updateOrInsert(['codigo' => $state['codigo']], $state + ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
            $memberState = DB::table('estados_membresia')->where('codigo', 'MIEMBRO')->value('id');

            DB::table('miembros')->updateOrInsert(['iglesia_id' => $church, 'tipo_documento' => 'DNI', 'numero_documento' => '00000000'], ['nombres' => 'Administrador', 'apellidos' => 'SIGE', 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'M', 'correo_electronico' => 'admin@mmm.local', 'telefono' => '999999999', 'direccion' => 'Dirección institucional', 'updated_at' => $now, 'created_at' => $now]);
            $memberId = DB::table('miembros')->where(['iglesia_id' => $church, 'tipo_documento' => 'DNI', 'numero_documento' => '00000000'])->value('id');
            DB::table('usuarios')->updateOrInsert(['miembro_id' => $memberId], ['nombre_usuario' => 'admin', 'contrasena' => Hash::make('Admin123*'), 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            $adminId = DB::table('usuarios')->where('miembro_id', $memberId)->value('id');
            DB::table('usuario_roles')->updateOrInsert(['usuario_id' => $adminId, 'rol_id' => $adminRole], ['asignado_por_usuario_id' => $adminId, 'asignado_at' => $now, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('historial_membresia')->updateOrInsert(['miembro_id' => $memberId, 'estado_membresia_id' => $memberState, 'fecha_inicio' => '2020-01-01'], ['registrado_por_usuario_id' => $adminId, 'updated_at' => $now, 'created_at' => $now]);

            foreach ([['codigo' => 'DOCUMENTO', 'nombre' => 'Documento'], ['codigo' => 'VIDEO', 'nombre' => 'Video'], ['codigo' => 'ENLACE', 'nombre' => 'Enlace']] as $type) {
                DB::table('tipos_material')->updateOrInsert(['codigo' => $type['codigo']], $type + ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
            foreach ([['codigo' => 'ACADEMICO', 'nombre' => 'Certificado académico', 'categoria' => 'certificado'], ['codigo' => 'RECOMENDACION', 'nombre' => 'Carta de recomendación', 'categoria' => 'recomendacion']] as $type) {
                DB::table('tipos_certificado')->updateOrInsert(['codigo' => $type['codigo']], $type + ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
            foreach ([['codigo' => 'DOMINICAL', 'nombre' => 'Culto dominical'], ['codigo' => 'ORACION', 'nombre' => 'Culto de oración']] as $type) {
                DB::table('tipos_culto')->updateOrInsert(['iglesia_id' => $church, 'codigo' => $type['codigo']], $type + ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
            foreach ([['codigo' => 'PREDICACION', 'nombre' => 'Predicación'], ['codigo' => 'ALABANZA', 'nombre' => 'Alabanza']] as $type) {
                DB::table('tipos_participacion')->updateOrInsert(['codigo' => $type['codigo']], $type + ['requiere_miembro' => true, 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
        });
    }
}
