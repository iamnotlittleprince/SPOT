<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        DB::table('profiles')->upsert([
            ['name' => 'Administrador', 'slug' => 'administrador', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gestor', 'slug' => 'gestor', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Analista', 'slug' => 'analista', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Convidado', 'slug' => 'convidado', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Administrador e Gestor', 'slug' => 'adm-gest', 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['name', 'updated_at']);

        User::firstOrCreate(
            ['email' => 'admin@estoque.com'],
            ['name' => 'Administrador', 'password' => 'senha123'],
        );

        foreach ([
            'Papelaria' => 'Cadernos, canetas e papéis.',
            'Eletrônicos' => 'Cabos, adaptadores e acessórios.',
            'Bebidas' => 'Refrigerantes e sucos.',
        ] as $name => $description) {
            Category::updateOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
