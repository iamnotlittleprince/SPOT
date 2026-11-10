<?php

namespace App\Domain\Mail;

final class SystemMailCopy
{
    public static function for(string $type, ?string $locale = null): array
    {
        $locale = in_array($locale, ['pt', 'en', 'es'], true) ? $locale : 'pt';
        $common = match ($locale) {
            'en' => ['fallback' => 'If the button does not work, copy and paste this link into your browser:', 'footer' => 'Spot team · Automated message', 'note' => 'For your security, do not share this link.'],
            'es' => ['fallback' => 'Si el botón no funciona, copia y pega este enlace en tu navegador:', 'footer' => 'Equipo de Spot · Mensaje automático', 'note' => 'Por tu seguridad, no compartas este enlace.'],
            default => ['fallback' => 'Se o botão não funcionar, copie e cole este link no navegador:', 'footer' => 'Equipe Spot · Mensagem automática', 'note' => 'Para sua segurança, não compartilhe este link.'],
        };
        $messages = match ($locale) {
            'en' => [
                'activation' => ['subject' => 'First access to Spot', 'tag' => 'ACCOUNT ACCESS', 'title' => 'Activate your Spot account', 'intro' => 'An administrator created your account. Confirm your details and create your password to start using Spot.', 'button' => 'Activate my account', 'expiry' => 'This personal link can only be used once. It expires on :date.', 'ignore' => 'If you were not expecting this account, contact your administrator.'],
                'invitation' => ['subject' => 'Project invitation — Spot', 'tag' => 'PROJECT INVITATION', 'title' => 'You have been invited to Spot', 'intro' => ':inviter invited you to view the project :project.', 'button' => 'Accept invitation', 'expiry' => 'This personal link can only be used once and expires in :hours hours.', 'ignore' => 'If you were not expecting this invitation, you can ignore this message.'],
                'activity' => ['subject' => 'Updates from Spot', 'daily' => 'Your daily Spot summary', 'weekly' => 'Your weekly Spot summary', 'tag' => 'YOUR WORKSPACE', 'title' => 'Updates from Spot', 'intro' => 'See the latest updates in your workspace.', 'button' => 'Open Spot', 'preferences' => 'You can adjust email channels, summaries and quiet hours in My profile → Notifications.'],
            ],
            'es' => [
                'activation' => ['subject' => 'Primer acceso a Spot', 'tag' => 'ACCESO A LA CUENTA', 'title' => 'Activa tu cuenta de Spot', 'intro' => 'Un administrador creó tu cuenta. Confirma tus datos y crea tu contraseña para empezar a usar Spot.', 'button' => 'Activar mi cuenta', 'expiry' => 'Este enlace personal solo se puede usar una vez. Caduca el :date.', 'ignore' => 'Si no esperabas esta cuenta, contacta con tu administrador.'],
                'invitation' => ['subject' => 'Invitación al proyecto — Spot', 'tag' => 'INVITACIÓN AL PROYECTO', 'title' => 'Recibiste una invitación a Spot', 'intro' => ':inviter te invitó a ver el proyecto :project.', 'button' => 'Aceptar invitación', 'expiry' => 'Este enlace personal solo se puede usar una vez y caduca en :hours horas.', 'ignore' => 'Si no esperabas esta invitación, puedes ignorar este mensaje.'],
                'activity' => ['subject' => 'Actualizaciones de Spot', 'daily' => 'Tu resumen diario de Spot', 'weekly' => 'Tu resumen semanal de Spot', 'tag' => 'TU ESPACIO DE TRABAJO', 'title' => 'Actualizaciones de Spot', 'intro' => 'Consulta las últimas actualizaciones de tu espacio de trabajo.', 'button' => 'Abrir Spot', 'preferences' => 'Puedes ajustar los canales de correo, los resúmenes y el horario de silencio en Mi perfil → Notificaciones.'],
            ],
            default => [
                'activation' => ['subject' => 'Primeiro acesso ao Spot', 'tag' => 'ACESSO À CONTA', 'title' => 'Ative sua conta no Spot', 'intro' => 'Um administrador criou sua conta. Confirme seus dados e crie sua senha para começar a usar o Spot.', 'button' => 'Ativar minha conta', 'expiry' => 'Este link pessoal pode ser usado uma única vez. Ele expira em :date.', 'ignore' => 'Se você não esperava esta conta, entre em contato com seu administrador.'],
                'invitation' => ['subject' => 'Convite para o projeto — Spot', 'tag' => 'CONVITE PARA PROJETO', 'title' => 'Você recebeu um convite para o Spot', 'intro' => ':inviter convidou você para visualizar o projeto :project.', 'button' => 'Aceitar convite', 'expiry' => 'Este link pessoal pode ser usado uma única vez e expira em :hours horas.', 'ignore' => 'Se você não esperava este convite, ignore esta mensagem.'],
                'activity' => ['subject' => 'Atualizações do Spot', 'daily' => 'Seu resumo diário do Spot', 'weekly' => 'Seu resumo semanal do Spot', 'tag' => 'SUA ÁREA DE TRABALHO', 'title' => 'Atualizações do Spot', 'intro' => 'Confira as últimas atualizações da sua área de trabalho.', 'button' => 'Abrir o Spot', 'preferences' => 'Você pode ajustar os canais, resumos e o horário de silêncio em Meu perfil → Notificações.'],
            ],
        };

        return $common + $messages[$type] + ['mailLanguage' => $locale === 'pt' ? 'pt-BR' : $locale];
    }
}
