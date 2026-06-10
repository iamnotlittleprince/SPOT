<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('tb_perfil')->upsert([
            ['id_perfil' => 1, 'perfil' => 'Administrador'],
            ['id_perfil' => 2, 'perfil' => 'Gestor'],
            ['id_perfil' => 3, 'perfil' => 'Analista'],
            ['id_perfil' => 4, 'perfil' => 'Convidado'],
            ['id_perfil' => 5, 'perfil' => 'AdmGest'],
        ], ['id_perfil'], ['perfil']);

        DB::table('tb_usuario')->updateOrInsert(
            ['Email' => 'admin@spot.local'],
            [
                'Senha' => Hash::make('password'),
                'id_perfil' => 1,
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
