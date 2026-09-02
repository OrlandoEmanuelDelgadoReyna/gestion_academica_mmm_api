<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas_tarea', function (Blueprint $table): void {
            $table->string('nombre_original', 255)->nullable()->after('ruta_archivo');
            $table->string('mime', 191)->nullable()->after('nombre_original');
            $table->unsignedBigInteger('tamano_bytes')->nullable()->after('mime');
        });
    }

    public function down(): void
    {
        Schema::table('entregas_tarea', function (Blueprint $table): void {
            $table->dropColumn(['nombre_original', 'mime', 'tamano_bytes']);
        });
    }
};
