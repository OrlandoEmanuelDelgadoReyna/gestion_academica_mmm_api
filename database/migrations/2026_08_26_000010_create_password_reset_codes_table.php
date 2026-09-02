<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores hashed one-time password recovery codes.
     *
     * With MAIL_MAILER=log, the plaintext code is written to storage/logs/laravel.log
     * by the mailer. It is never stored in this table or returned in JSON.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_reset_codes')) {
            return;
        }

        Schema::create('password_reset_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->string('codigo_hash', 255);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
            $table->index('expires_at');
            $table->index(['usuario_id', 'used_at', 'expires_at'], 'password_reset_codes_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
