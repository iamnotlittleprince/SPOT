<?php

use App\Domain\Projects\ProjectFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('audit_retention_months')->default(12);
            $table->string('aad_tenant_id')->nullable();
            $table->string('aad_client_id')->nullable();
            $table->text('aad_client_secret')->nullable();
            $table->timestamps();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('cost_rate_snapshot', 15, 2)->nullable();
            $table->decimal('sale_rate_snapshot', 15, 2)->nullable();
        });
        foreach (ProjectFields::LABELS as $field => $label) {
            foreach (['view' => 'Visualizar', 'edit' => 'Alterar'] as $operation => $verb) {
                DB::table('permissions')->updateOrInsert(['slug' => "projects.fields.$field.$operation"], ['name' => "$verb: $label", 'module' => 'Campos do projeto', 'description' => "$verb: $label", 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach (['tasks.view_others' => 'Ver atividades de outros analistas', 'tasks.update_others' => 'Alterar atividades de outros analistas', 'tasks.delete_others' => 'Excluir atividades de outros analistas'] as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name, 'module' => 'Tarefas', 'description' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('profiles')->whereIn('slug', ['administrador', 'gestor-administrador', 'gestor'])->pluck('id') as $profile) {
                DB::table('permission_profile')->insertOrIgnore(['profile_id' => $profile, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['cost_rate_snapshot', 'sale_rate_snapshot']));
        DB::table('permissions')->whereIn('slug', ['tasks.view_others', 'tasks.update_others', 'tasks.delete_others'])->delete();
    }
};
