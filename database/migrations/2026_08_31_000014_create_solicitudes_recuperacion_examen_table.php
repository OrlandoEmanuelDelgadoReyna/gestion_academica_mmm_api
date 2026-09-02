<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery requests for a failed final exam.
 *
 * Why a new table: no existing table models a request/approval workflow
 * between alumno and teacher. Intentos and notas store academic results,
 * not the petition itself. Unique active-request is enforced in the
 * application (pendiente/aprobada) so a rejected student can apply again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitudes_recuperacion_examen')) {
            return;
        }

        Schema::create('solicitudes_recuperacion_examen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_final_id')->constrained('examenes_finales')->restrictOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
            $table->string('estado', 30);
            $table->dateTime('solicitada_at');
            $table->foreignId('atendida_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->dateTime('atendida_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->index(['examen_final_id', 'matricula_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_recuperacion_examen');
    }
};
