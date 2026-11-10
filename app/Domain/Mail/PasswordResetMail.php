<?php
namespace App\Domain\Mail;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetMail
{
    public function message(User $user, string $token, string $locale): MailMessage
    {
        $locale = in_array($locale, ['pt', 'en', 'es'], true) ? $locale : 'pt';
        $copy = match ($locale) {
            'en' => [
                'subject' => 'Reset your Spot password', 'tag' => 'ACCOUNT ACCESS', 'title' => 'Create a new password',
                'intro' => 'We received a request to reset your Spot password.', 'button' => 'Reset password',
                'expiry' => 'This link expires in :minutes minutes and can only be used once.',
                'ignore' => 'If you did not request this change, you can ignore this message. Your password will stay the same.',
                'fallback' => 'If the button does not work, copy and paste this link into your browser:',
                'footer' => 'Spot team · Automated message', 'note' => 'For your security, do not share this link.',
            ],
            'es' => [
                'subject' => 'Restablece tu contraseña de Spot', 'tag' => 'ACCESO A LA CUENTA', 'title' => 'Crea una nueva contraseña',
                'intro' => 'Recibimos una solicitud para restablecer tu contraseña de Spot.', 'button' => 'Restablecer contraseña',
                'expiry' => 'Este enlace caduca en :minutes minutos y solo se puede usar una vez.',
                'ignore' => 'Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña seguirá siendo la misma.',
                'fallback' => 'Si el botón no funciona, copia y pega este enlace en tu navegador:',
                'footer' => 'Equipo de Spot · Mensaje automático', 'note' => 'Por tu seguridad, no compartas este enlace.',
            ],
            default => [
                'subject' => 'Redefina sua senha do Spot', 'tag' => 'ACESSO À CONTA', 'title' => 'Crie uma nova senha',
                'intro' => 'Recebemos uma solicitação para redefinir a senha da sua conta no Spot.', 'button' => 'Redefinir senha',
                'expiry' => 'Este link expira em :minutes minutos e pode ser usado apenas uma vez.',
                'ignore' => 'Se você não solicitou esta alteração, pode ignorar esta mensagem. Sua senha continuará a mesma.',
                'fallback' => 'Se o botão não funcionar, copie e cole este link no navegador:',
                'footer' => 'Equipe Spot · Mensagem automática', 'note' => 'Para sua segurança, não compartilhe este link.',
            ],
        };
        $copy['expiry'] = str_replace(':minutes', (string) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60), $copy['expiry']);
        $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset(), 'lang' => $locale]);
        return (new MailMessage)->subject($copy['subject'])->view(
            ['html' => 'mail.password-reset', 'text' => 'mail.password-reset-text'],
            ['copy' => $copy, 'resetUrl' => $url, 'mailLanguage' => $locale === 'pt' ? 'pt-BR' : $locale]
        );
    }
}
