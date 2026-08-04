<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de iglesias institucionales.
     */
    public function up(): void
    {
        Schema::create('iglesias', function (Blueprint $table): void {
            $table->id()->comment('Clave primaria de la iglesia.');
            $table->string('codigo', 30)->unique()->comment('Código institucional único.');
            $table->string('nombre', 150)->comment('Nombre oficial de la iglesia.');
            $table->string('direccion', 255)->nullable()->comment('Dirección institucional.');
            $table->string('telefono', 30)->nullable()->comment('Teléfono institucional de contacto.');
            $table->string('correo_electronico', 150)->nullable()->comment('Correo electrónico institucional.');
            $table->boolean('activo')->default(true)->comment('Indica si la iglesia se encuentra operativa.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'deleted_at'], 'iglesias_activo_eliminado_indice');
        });
    }

    /**
     * Elimina la tabla de iglesias institucionales.
     */
    public function down(): void
    {
        Schema::dropIfExists('iglesias');
    }
};
