<?php

// Synthetic previews only: no database writes or email delivery.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = $argv[1] ?? sys_get_temp_dir();
if (!is_dir($directory)) throw new InvalidArgumentException('Informe um diretório existente para as prévias.');
$project = new App\Models\Project(['name' => 'Projeto de exemplo — organização e planejamento']);
foreach (['pt', 'en', 'es'] as $locale) {
    $url = 'https://example.invalid/action/preview-token?lang='.$locale;
    $mails = [
        'activation' => new App\Mail\AccountActivationMail($url, '09/11/2026 18:00 UTC', $locale),
        'invitation' => new App\Mail\ProjectInvitationMail($project, $url, 'Equipe de projetos', 24, $locale),
        'activity' => new App\Mail\ActivityNotificationMail('immediate', [['title' => 'Atualização de exemplo', 'body' => 'Conteúdo de exemplo da atualização. Os textos da atividade são preservados.', 'actor_name' => 'Equipe de projetos']], $locale),
        'daily' => new App\Mail\ActivityNotificationMail('daily', [['title' => 'Projeto de exemplo', 'body' => 'Conteúdo de exemplo do resumo diário.', 'actor_name' => 'Equipe de projetos']], $locale),
        'weekly' => new App\Mail\ActivityNotificationMail('weekly', [['title' => str_repeat('Nome-longo-', 10), 'body' => str_repeat('Texto de atualização. ', 10), 'actor_name' => 'Equipe de projetos']], $locale),
    ];
    foreach ($mails as $type => $mail) file_put_contents($directory.'/spot-'.$type.'-'.$locale.'.html', $mail->render());
    $reset = app(App\Domain\Mail\PasswordResetMail::class)->message(new App\Models\User(['email' => 'preview@example.invalid']), 'preview-token', $locale);
    file_put_contents($directory.'/spot-reset-'.$locale.'.html', view($reset->view['html'], $reset->viewData)->render());
}
