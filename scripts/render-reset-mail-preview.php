<?php

// Local previews use a synthetic account/token and never send mail or persist a user.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = $argv[1] ?? sys_get_temp_dir();
if (!is_dir($directory)) throw new InvalidArgumentException('Informe um diretório existente para as prévias.');
$user = new App\Models\User(['email' => 'preview@example.invalid']);
foreach (['pt', 'en', 'es'] as $locale) {
    $mail = app(App\Domain\Mail\PasswordResetMail::class)->message($user, 'preview-token', $locale);
    file_put_contents($directory.'/spot-reset-'.$locale.'.html', view($mail->view['html'], $mail->viewData)->render());
}
