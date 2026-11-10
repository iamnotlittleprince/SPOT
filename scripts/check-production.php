<?php

// Read-only checks. Never print credentials or send email/notifications.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$blockers = [];
$notes = [];
if (! $app->isProduction()) $blockers[] = 'APP_ENV deve ser production no servidor de produção.';
if (config('app.debug')) $blockers[] = 'APP_DEBUG deve ser false.';
if (parse_url(config('app.url'), PHP_URL_SCHEME) !== 'https' || in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', 'spot.seudominio.com'], true)) $blockers[] = 'APP_URL deve usar a origem HTTPS definitiva.';
if (! config('app.key')) $blockers[] = 'APP_KEY não configurada.';
if (str_starts_with((string) config('jwt.algo'), 'HS') && !config('jwt.secret')) $blockers[] = 'JWT_SECRET não configurada para a API de estoque e tokens.';
if (! config('session.secure')) $blockers[] = 'SESSION_SECURE_COOKIE deve ser true em HTTPS.';
if (! config('session.http_only')) $blockers[] = 'SESSION_HTTP_ONLY deve ser true.';
if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) $notes[] = 'Revise SameSite e os retornos OAuth neste domínio.';
if (in_array(config('mail.default'), ['log', 'array'], true)) $blockers[] = 'Transporte de e-mail real não configurado.';
if (config('mail.default') === 'smtp' && !config('mail.mailers.smtp.host')) $blockers[] = 'Configure o host SMTP.';
if (! config('mail.from.address') || str_ends_with((string) config('mail.from.address'), '@example.com')) $blockers[] = 'Configure o remetente real do e-mail.';
if (is_file(public_path('hot'))) $blockers[] = 'Remova public/hot do artefato de produção; ele aponta para o Vite de desenvolvimento.';
$manifestPath = public_path('build/manifest.json');
if (!is_file($manifestPath)) $blockers[] = 'Build do frontend ausente.';
else {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (!isset($manifest['resources/js/app.jsx'])) $blockers[] = 'Entrada do frontend não encontrada no manifest.';
    foreach ($manifest ?? [] as $entry) {
        foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? []) as $file) {
            if ($file && !is_file(public_path('build/'.$file))) $blockers[] = 'Arquivo do build ausente: '.$file;
        }
    }
}
if (! config('services.chat_translation.url')) $notes[] = 'Tradução das mensagens de chat indisponível sem CHAT_TRANSLATION_URL.';
if (!is_writable(storage_path()) || !is_writable(base_path('bootstrap/cache'))) $blockers[] = 'storage e bootstrap/cache precisam de escrita pelo processo da aplicação.';
try {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    $migrator = app('migrator');
    $files = $migrator->getMigrationFiles(database_path('migrations'));
    $ran = $migrator->getRepository()->getRan();
    $pending = array_values(array_diff(array_keys($files), $ran));
    if ($pending) $blockers[] = 'Migrations pendentes: '.implode(', ', $pending);
} catch (Throwable $exception) { $blockers[] = 'Não foi possível validar o banco e as migrations. Confira a conexão no servidor.'; }
$notes[] = 'Validar workers (fila emails/default), scheduler por minuto, banco, backups, SMTP e OAuth no servidor de destino.';
echo json_encode(['checks_passed' => !$blockers, 'blockers' => array_values(array_unique($blockers)), 'notes' => $notes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($blockers ? 1 : 0);
