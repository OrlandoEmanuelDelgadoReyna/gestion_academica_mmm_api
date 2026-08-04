<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las tablas de identidad, autorización y auditoría.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index('activo');
        });

        Schema::create('permisos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('modulo', 60);
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['modulo', 'activo']);
        });

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnUpdate()->restrictOnDelete();
            $table->string('nombre_usuario', 60)->unique();
            $table->string('contrasena', 255);
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('miembro_id');
            $table->index(['activo', 'deleted_at']);
        });

        Schema::create('usuario_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('asignado_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('asignado_at')->useCurrent();
            $table->timestamps();
            $table->unique(['usuario_id', 'rol_id']);
            $table->index('rol_id');
        });

        Schema::create('rol_permisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('asignado_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('asignado_at')->useCurrent();
            $table->timestamps();
            $table->unique(['rol_id', 'permiso_id']);
            $table->index('permiso_id');
        });

        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->string('accion', 80);
            $table->string('tabla_afectada', 100);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->string('dispositivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tabla_afectada', 'registro_id']);
            $table->index(['usuario_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Elimina las tablas de identidad, autorización y auditoría.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('rol_permisos');
        Schema::dropIfExists('usuario_roles');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
