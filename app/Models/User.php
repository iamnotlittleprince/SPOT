<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['person_id', 'current_company_id', 'organization_id', 'name', 'job_title', 'department', 'email', 'password', 'google_id', 'google_email', 'microsoft_id', 'microsoft_email', 'google_access_token', 'google_refresh_token', 'google_token_expires_at', 'microsoft_access_token', 'microsoft_refresh_token', 'microsoft_token_expires_at', 'avatar_url', 'email_verified_at', 'active', 'account_status', 'timezone', 'last_login_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'notify_new_login', 'notify_password_change', 'notify_provider_link', 'notification_preferences'])]
#[Hidden(['password', 'remember_token', 'google_id', 'microsoft_id', 'google_access_token', 'google_refresh_token', 'microsoft_access_token', 'microsoft_refresh_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = ['active' => true, 'account_status' => 'active'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user_profiles')
            ->withPivot('profile_id')->withTimestamps();
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'company_user_profiles')
            ->withPivot('company_id')->withTimestamps();
    }

    public function spotRoleLabel(): string
    {
        $roles = $this->profiles()->wherePivot('company_id', $this->current_company_id)->pluck('slug');
        foreach (['administrador' => 'Administrador', 'gestor-administrador' => 'Administrador', 'gestor' => 'Gestor', 'analista' => 'Analista', 'convidado' => 'Convidado'] as $slug => $label) {
            if ($roles->contains($slug)) {
                return $label;
            }
        }

        return 'Perfil não definido';
    }

    public function canCreateTasks(): bool
    {
        $profiles = $this->profiles()->wherePivot('company_id', $this->current_company_id);

        return $this->active
            && (clone $profiles)->exists()
            && ! (clone $profiles)->where('slug', 'convidado')->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->active && $this->profiles()->wherePivot('company_id', $this->current_company_id)->whereIn('slug', ['administrador', 'gestor-administrador'])->exists()) return true;

        $override = $this->belongsToMany(Permission::class, 'user_permission_overrides')
            ->withPivot('allowed')->where('slug', $permission)->first();

        if ($override) {
            return (bool) $override->pivot->allowed;
        }

        return $this->profiles()
            ->wherePivot('company_id', $this->current_company_id)
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'notify_new_login' => 'boolean',
            'notify_password_change' => 'boolean',
            'notify_provider_link' => 'boolean',
            'notification_preferences' => 'array',
            'google_access_token' => 'encrypted', 'google_refresh_token' => 'encrypted', 'google_token_expires_at' => 'datetime',
            'microsoft_access_token' => 'encrypted', 'microsoft_refresh_token' => 'encrypted', 'microsoft_token_expires_at' => 'datetime',
        ];
    }
}
