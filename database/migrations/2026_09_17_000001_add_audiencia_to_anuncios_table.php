<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anuncios', function (Blueprint $table): void {
            if (! Schema::hasColumn('anuncios', 'audiencia')) {
                $table->string('audiencia', 20)->default('todos')->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anuncios', function (Blueprint $table): void {
            if (Schema::hasColumn('anuncios', 'audiencia')) {
                $table->dropColumn('audiencia');
            }
        });
    }
};
