<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table): void {
            if (! Schema::hasColumn('cursos', 'portada_path')) {
                $table->string('portada_path', 255)->nullable()->after('descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table): void {
            if (Schema::hasColumn('cursos', 'portada_path')) {
                $table->dropColumn('portada_path');
            }
        });
    }
};
