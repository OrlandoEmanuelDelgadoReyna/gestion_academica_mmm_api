<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examenes_finales', function (Blueprint $table): void {
            $table->string('recuperacion_titulo', 150)->nullable()->after('activo');
            $table->text('recuperacion_descripcion')->nullable()->after('recuperacion_titulo');
            $table->timestamp('recuperacion_at')->nullable()->after('recuperacion_descripcion');
            $table->timestamp('recuperacion_generada_at')->nullable()->after('recuperacion_at');
        });
    }

    public function down(): void
    {
        Schema::table('examenes_finales', function (Blueprint $table): void {
            $table->dropColumn([
                'recuperacion_titulo',
                'recuperacion_descripcion',
                'recuperacion_at',
                'recuperacion_generada_at',
            ]);
        });
    }
};
