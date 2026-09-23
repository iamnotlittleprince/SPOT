<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::query()->where('trade_name', 'Computécnica')->value('id')
            ?? Company::query()->value('id');

        if (! $companyId) {
            return;
        }

        $now = now();
        $clients = [
            ['name' => 'Alfa Indústria', 'legal_name' => 'Alfa Indústria e Tecnologia Ltda.', 'document' => '11222333000181', 'email' => 'projetos@alfaindustria.demo', 'phone' => '(11) 3001-1001'],
            ['name' => 'Beta Logística', 'legal_name' => 'Beta Logística Integrada Ltda.', 'document' => '11444777000161', 'email' => 'ti@betalogistica.demo', 'phone' => '(11) 3002-2002'],
            ['name' => 'Gamma Serviços', 'legal_name' => 'Gamma Serviços Corporativos S.A.', 'document' => '19131243000197', 'email' => 'operacoes@gammaservicos.demo', 'phone' => '(21) 3003-3003'],
            ['name' => 'Delta Comércio', 'legal_name' => 'Delta Comércio e Atendimento Ltda.', 'document' => '27865757000102', 'email' => 'atendimento@deltacomercio.demo', 'phone' => '(31) 3004-4004'],
            ['name' => 'Épsilon Saúde', 'legal_name' => 'Épsilon Soluções em Saúde Ltda.', 'document' => '45997418000153', 'email' => 'projetos@epsilonsaude.demo', 'phone' => '(11) 3005-5005'],
            ['name' => 'Zeta Educação', 'legal_name' => 'Zeta Educação Digital S.A.', 'document' => '06990590000123', 'email' => 'tecnologia@zetaeducacao.demo', 'phone' => '(41) 3006-6006'],
        ];

        foreach ($clients as $client) {
            DB::table('clients')->updateOrInsert(
                ['company_id' => $companyId, 'document' => $client['document']],
                [...$client, 'active' => true, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('projects')->where('company_id', $companyId)->whereNotNull('client_name')->get(['id', 'client_name'])->each(function ($project) use ($companyId): void {
            $clientId = DB::table('clients')->where('company_id', $companyId)->where('name', $project->client_name)->value('id');
            if ($clientId) {
                DB::table('projects')->where('id', $project->id)->update(['client_id' => $clientId]);
            }
        });
    }
}
