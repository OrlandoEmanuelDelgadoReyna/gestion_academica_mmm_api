<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores registered exam grades (manual or synced from a completed attempt).
 *
 * Why a new table: intentos_examen already represents an interactive sitting
 * (start, answers, server-side score). Reusing it for an external/paper
 * grade would mix two concepts. calificaciones stores the consolidated
 * course result, not a per-exam score. This table is the canonical
 * exam+matrícula grade, including original and recovery notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notas_examen_final')) {
            return;
        }

        Schema::create('notas_examen_final', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_final_id')->constrained('examenes_finales')->restrictOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
            $table->decimal('nota', 6, 2)->nullable();
            $table->decimal('nota_recuperacion', 6, 2)->nullable();
            $table->foreignId('calificado_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->dateTime('calificado_at')->nullable();
            $table->foreignId('recuperacion_calificado_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->dateTime('recuperacion_calificado_at')->nullable();
            $table->string('origen', 30)->default('manual');
            $table->timestamps();
            $table->unique(['examen_final_id', 'matricula_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_examen_final');
    }
};
