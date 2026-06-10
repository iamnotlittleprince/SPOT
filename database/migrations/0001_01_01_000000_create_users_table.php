<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_perfil', function (Blueprint $table) {
            $table->id('id_perfil');
            $table->string('perfil', 40)->unique();
            $table->timestamps();
        });

        DB::table('tb_perfil')->insert([
            ['perfil' => 'Administrador', 'created_at' => now(), 'updated_at' => now()],
            ['perfil' => 'Gestor', 'created_at' => now(), 'updated_at' => now()],
            ['perfil' => 'Analista', 'created_at' => now(), 'updated_at' => now()],
            ['perfil' => 'Convidado', 'created_at' => now(), 'updated_at' => now()],
            ['perfil' => 'AdmGest', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('tb_pessoa', function (Blueprint $table) {
            $table->id('id_pessoa');
            $table->string('CPF', 14)->nullable();
            $table->timestamps();
        });

        Schema::create('tb_empresa', function (Blueprint $table) {
            $table->id('id_empresa');
            $table->string('CNPJ', 18)->nullable();
            $table->timestamps();
        });

        Schema::create('tb_cliente', function (Blueprint $table) {
            $table->id('id_cliente');
            $table->string('NomeCliente', 120);
            $table->timestamps();
        });

        Schema::create('tb_clientetipo', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pessoa_empresa');
            $table->foreignId('id_cliente')
                ->constrained('tb_cliente', 'id_cliente')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('tipo', 10);
            $table->timestamps();

            $table->primary(['id_pessoa_empresa', 'id_cliente', 'tipo']);
        });

        Schema::create('tb_usuario', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('Email', 120)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('Senha');
            $table->foreignId('id_perfil')
                ->constrained('tb_perfil', 'id_perfil')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pessoa')
                ->nullable()
                ->constrained('tb_pessoa', 'id_pessoa')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('tb_usuario', 'id_usuario')
                ->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_usuario');
        Schema::dropIfExists('tb_clientetipo');
        Schema::dropIfExists('tb_cliente');
        Schema::dropIfExists('tb_empresa');
        Schema::dropIfExists('tb_pessoa');
        Schema::dropIfExists('tb_perfil');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
