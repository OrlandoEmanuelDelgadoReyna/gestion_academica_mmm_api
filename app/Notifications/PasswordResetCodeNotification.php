<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a 6-digit recovery code.
 *
 * Development: with MAIL_MAILER=log the code is written to storage/logs/laravel.log.
 * It is never included in HTTP JSON or APP_DEBUG payloads.
 */
final class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de recuperación de contraseña')
            ->greeting('Hola '.$this->nombre)
            ->line('Usa este código de 6 dígitos para restablecer tu contraseña:')
            ->line($this->codigo)
            ->line('El código vence en 10 minutos.')
            ->line('No compartas este código con nadie. El equipo de SIGE MMM nunca te lo pedirá.');
    }
}
