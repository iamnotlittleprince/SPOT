<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['legal_name', 'trade_name', 'cnpj'];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'company_people')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user_profiles')
            ->withPivot('profile_id')->withTimestamps();
    }
}
