<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:status', function () {
    $connection = config('queue.connections.database.connection') ?: config('database.default');
    $jobs = DB::connection($connection)
        ->table(config('queue.connections.database.table'));
    $failed = DB::connection(config('queue.failed.database'))
        ->table(config('queue.failed.table'))->count();

    $this->table(['Fila no banco', 'Aguardando (inclui novas tentativas)', 'Reservadas'], $jobs
        ->selectRaw('queue, SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END) AS waiting, SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved')
        ->groupBy('queue')->get()->map(fn ($row) => [$row->queue, $row->waiting, $row->reserved])->all());
    $this->info("Falhas registradas: {$failed}. Consulte queue:failed para detalhes.");
})->purpose('Mostra tarefas e falhas da fila armazenada no banco');

Artisan::command('audit:prune {--dry-run}', function () {
    $total = 0;
    foreach (\App\Models\Company::all() as $company) {
        $months = \App\Models\CompanySetting::where('company_id', $company->id)->value('audit_retention_months') ?? 12;
        $query = DB::table('audit_logs')->where('company_id', $company->id)->where('created_at', '<', now()->subMonthsNoOverflow($months));
        $total += $this->option('dry-run') ? $query->count() : $query->delete();
    }
    $this->info(($this->option('dry-run') ? 'Registros elegíveis: ' : 'Registros removidos: ').$total);
})->purpose('Aplica a retenção de auditoria por empresa (12 meses por padrão)');
\Illuminate\Support\Facades\Schedule::command('audit:prune')->dailyAt('03:00')->withoutOverlapping();

Artisan::command('inbox:prune {--dry-run}', function () {
    $query = \App\Models\InboxItem::whereNotNull('archived_at')->where('archived_at', '<', now()->subDays(30));
    $total = $this->option('dry-run') ? $query->count() : $query->delete();
    $this->info(($this->option('dry-run') ? 'Mensagens elegíveis: ' : 'Mensagens removidas: ').$total);
})->purpose('Remove mensagens que estão na lixeira há mais de 30 dias');
\Illuminate\Support\Facades\Schedule::command('inbox:prune')->dailyAt('03:15')->withoutOverlapping();
