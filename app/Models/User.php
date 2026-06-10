<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'tb_usuario';

    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'Email',
        'Senha',
        'email_verified_at',
        'id_perfil',
        'id_pessoa',
    ];

    protected $hidden = [
        'Senha',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'Senha' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->Senha;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->Email;
    }
}
