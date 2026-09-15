<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links announcement-generated notices to their source anuncio.
 *
 * Why a new migration: notificaciones already exists without an origin FK.
 * anuncio_id is nullable because task/exam/certificate/general notices are
 * not tied to an announcement. restrictOnDelete is required because
 * notificacion_destinatarios still uses restrictOnDelete; AnuncioService
 * must delete recipients and notices before the anuncio row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notificaciones') || Schema::hasColumn('notificaciones', 'anuncio_id')) {
            return;
        }

        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->foreignId('anuncio_id')
                ->nullable()
                ->after('tipo')
                ->constrained('anuncios')
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notificaciones') || ! Schema::hasColumn('notificaciones', 'anuncio_id')) {
            return;
        }

        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anuncio_id');
        });
    }
};
