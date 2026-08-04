<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_certificado', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->string('categoria', 30);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('certificados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnDelete();
            $table->foreignId('tipo_certificado_id')->constrained('tipos_certificado')->restrictOnDelete();
            $table->foreignId('programacion_academica_id')->nullable()->constrained('programaciones_academicas')->restrictOnDelete();
            $table->foreignId('certificado_reemplazado_id')->nullable()->constrained('certificados')->restrictOnDelete();
            $table->string('codigo_verificacion', 80)->unique();
            $table->dateTime('emitido_at');
            $table->string('estado', 30);
            $table->string('destinatario', 150)->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('vence_at')->nullable();
            $table->string('ruta_documento', 2048)->nullable();
            $table->foreignId('firmado_por_miembro_id')->nullable()->constrained('miembros')->restrictOnDelete();
            $table->dateTime('firmado_at')->nullable();
            $table->foreignId('autorizado_por_miembro_id')->nullable()->constrained('miembros')->restrictOnDelete();
            $table->dateTime('autorizado_at')->nullable();
            $table->foreignId('emitido_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['miembro_id', 'tipo_certificado_id']);
            $table->index(['estado', 'emitido_at']);
            $table->index('certificado_reemplazado_id');
        });
        Schema::create('tipos_culto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nombre', 120);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['iglesia_id', 'codigo']);
        });
        Schema::create('tipos_participacion', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->boolean('requiere_miembro')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('cultos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->foreignId('tipo_culto_id')->constrained('tipos_culto')->restrictOnDelete();
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->string('lugar', 150)->nullable();
            $table->string('estado', 30);
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['iglesia_id', 'inicio_at']);
            $table->index(['tipo_culto_id', 'inicio_at']);
        });
        Schema::create('bloques_culto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('culto_id')->constrained('cultos')->restrictOnDelete();
            $table->foreignId('tipo_participacion_id')->constrained('tipos_participacion')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('contenido', 500)->nullable();
            $table->timestamps();
            $table->unique(['culto_id', 'orden']);
            $table->index(['culto_id', 'tipo_participacion_id']);
        });
        Schema::create('participaciones_culto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bloque_culto_id')->constrained('bloques_culto')->restrictOnDelete();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnDelete();
            $table->string('estado', 30);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
            $table->unique(['bloque_culto_id', 'miembro_id']);
            $table->index(['miembro_id', 'estado']);
        });
        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->string('lugar', 150)->nullable();
            $table->string('estado', 30);
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['iglesia_id', 'inicio_at']);
        });
        Schema::create('anuncios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->string('estado', 30);
            $table->dateTime('publicado_at')->nullable();
            $table->dateTime('vence_at')->nullable();
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['iglesia_id', 'estado', 'publicado_at']);
        });
        Schema::create('notificaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->string('tipo', 30);
            $table->dateTime('enviado_at')->nullable();
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['iglesia_id', 'enviado_at']);
            $table->index('tipo');
        });
        Schema::create('notificacion_destinatarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notificacion_id')->constrained('notificaciones')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('estado', 30);
            $table->dateTime('entregado_at')->nullable();
            $table->dateTime('leido_at')->nullable();
            $table->timestamps();
            $table->unique(['notificacion_id', 'usuario_id']);
            $table->index(['usuario_id', 'estado', 'leido_at']);
        });
        Schema::create('dispositivos_notificacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('token_push', 512)->unique();
            $table->string('plataforma', 30);
            $table->string('nombre_dispositivo', 120)->nullable();
            $table->boolean('activo')->default(true);
            $table->dateTime('ultimo_uso_at')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'activo']);
        });
    }

    public function down(): void
    {
        foreach (['dispositivos_notificacion', 'notificacion_destinatarios', 'notificaciones', 'anuncios', 'eventos', 'participaciones_culto', 'bloques_culto', 'cultos', 'tipos_participacion', 'tipos_culto', 'certificados', 'tipos_certificado'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
