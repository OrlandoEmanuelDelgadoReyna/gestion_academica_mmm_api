<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds exam authorship and per-question activation.
 *
 * Why a new migration: historical 2026_07_23_000006 already created
 * examenes_finales and preguntas_examen. Authorship is needed for audit
 * of who configured the exam. activo on questions lets an admin hide a
 * question without deleting it. Neither column existed, so they cannot
 * be reused from another table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('examenes_finales') && ! Schema::hasColumn('examenes_finales', 'creado_por_usuario_id')) {
            Schema::table('examenes_finales', function (Blueprint $table): void {
                $table->foreignId('creado_por_usuario_id')
                    ->nullable()
                    ->after('activo')
                    ->constrained('usuarios')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('preguntas_examen') && ! Schema::hasColumn('preguntas_examen', 'activo')) {
            Schema::table('preguntas_examen', function (Blueprint $table): void {
                $table->boolean('activo')->default(true)->after('puntaje');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('examenes_finales') && Schema::hasColumn('examenes_finales', 'creado_por_usuario_id')) {
            Schema::table('examenes_finales', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('creado_por_usuario_id');
            });
        }

        if (Schema::hasTable('preguntas_examen') && Schema::hasColumn('preguntas_examen', 'activo')) {
            Schema::table('preguntas_examen', function (Blueprint $table): void {
                $table->dropColumn('activo');
            });
        }
    }
};
