<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::table('profiles')->where('slug', 'gestor')->update(['name' => 'Gerente']);
        DB::table('profiles')->where('slug', 'gestor-administrador')->update(['name' => 'Gerente administrador']);
    }
    public function down(): void
    {
        DB::table('profiles')->where('slug', 'gestor')->update(['name' => 'Gestor']);
        DB::table('profiles')->where('slug', 'gestor-administrador')->update(['name' => 'Gestor administrador']);
    }
};
