<?php

namespace App\Rules;

use App\Domain\Projects\ProjectManagers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class EligibleProjectManager implements ValidationRule
{
    public function __construct(private readonly int|string|null $companyId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ProjectManagers::query($this->companyId)->whereKey($value)->exists()) {
            $fail('Escolha um administrador global, gestor-administrador ou gestor ativo desta empresa.');
        }
    }
}
