<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla central de miembros.
     */
    public function up(): void
    {
        if (! Schema::hasTable('miembros')) {
            Schema::create('miembros', function (Blueprint $table): void {
                $table->id()->comment('Clave primaria del miembro.');
                $table->foreignId('iglesia_id')
                    ->comment('Iglesia institucional a la que pertenece el miembro.')
                    ->constrained('iglesias')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();
                $table->string('tipo_documento', 30)->comment('Tipo de documento validado por la aplicación.');
                $table->string('numero_documento', 30)->comment('Número de documento de identificación.');
                $table->string('nombres', 120)->comment('Nombres del miembro.');
                $table->string('apellidos', 120)->comment('Apellidos del miembro.');
                $table->date('fecha_nacimiento')->nullable()->comment('Fecha de nacimiento, cuando se disponga.');
                $table->char('sexo', 1)->nullable()->comment('Sexo registrado: M, F u O.');
                $table->string('correo_electronico', 150)->nullable()->comment('Correo electrónico de contacto.');
                $table->string('telefono', 30)->nullable()->comment('Teléfono principal de contacto.');
                $table->string('direccion', 255)->nullable()->comment('Dirección principal de contacto.');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['iglesia_id', 'tipo_documento', 'numero_documento'],
                    'miembros_documento_unico',
                );
                $table->index(['iglesia_id', 'apellidos', 'nombres'], 'miembros_iglesia_nombre_indice');
                $table->index(['iglesia_id', 'deleted_at'], 'miembros_iglesia_eliminado_indice');
            });
        }
    }

    /**
     * Elimina la tabla central de miembros.
     */
    public function down(): void
    {
        Schema::dropIfExists('miembros');
    }
};
