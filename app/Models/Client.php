<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'legal_name', 'document', 'email', 'phone', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function companyPeople(): BelongsToMany
    {
        return $this->belongsToMany(CompanyPerson::class, 'client_memberships')->withTimestamps();
    }
}
