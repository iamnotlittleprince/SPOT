<?php

namespace App\Http\Requests;

use App\Domain\Projects\ProjectFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->is('api/v1/*') && ($this->isMethod('PUT') || $this->isMethod('PATCH')) && $this->route('project')?->isFinalized()) {
            abort(409, 'Projeto finalizado não pode ser alterado.');
        }

        if ($this->isMethod('POST')) {
            return $this->user()?->can('projects.create') ?? false;
        }

        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $versioned = $this->is('api/v1/*');
        $companyId = $this->user()?->current_company_id;
        $projectId = $this->route('project')?->getKey();

        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'proposal_number' => [$versioned ? ($this->isMethod('POST') ? 'required' : 'sometimes') : 'nullable', 'string', 'max:80', Rule::unique('projects', 'proposal_number')->where('company_id', $companyId)->ignore($projectId)],
            'participation_type' => ['sometimes', Rule::in(['full_project', 'subproject', 'workstream', 'service_package', 'consulting', 'support'])],
            'external_project_name' => ['nullable', 'string', 'max:200'],
            'external_project_code' => ['nullable', 'string', 'max:100'],
            'cpt_scope' => ['nullable', 'string', 'max:5000'],
            'project_status_id' => [$versioned ? ($this->isMethod('POST') ? 'required' : 'sometimes') : 'nullable', 'integer', Rule::exists('project_statuses', 'id')->where('company_id', $companyId)->where('active', true)],
            'project_situation_id' => [$versioned ? ($this->isMethod('POST') ? 'required' : 'sometimes') : 'nullable', 'integer', Rule::exists('project_situations', 'id')->where('company_id', $companyId)->where('active', true)],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('company_id', $companyId)->where('active', true)],
            'account_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('current_company_id', $companyId)->where('active', true)],
            'project_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('current_company_id', $companyId)->where('active', true)],
            'proposal_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_date' => ['nullable', 'date'],
            'contract_value' => [$versioned ? ($this->isMethod('POST') ? 'required' : 'sometimes') : 'nullable', 'numeric', 'min:0'],
            'taxes' => ['sometimes', 'array'],
            'taxes.*.tax_type_id' => ['required', 'integer', 'distinct', Rule::exists('tax_types', 'id')->where('company_id', $companyId)],
            'taxes.*.calculation_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'taxes.*.rate' => ['nullable', 'required_if:taxes.*.calculation_type,percentage', 'numeric', 'between:0,100'],
            'taxes.*.fixed_amount' => ['nullable', 'required_if:taxes.*.calculation_type,fixed', 'numeric', 'min:0'],
            'taxes.*.calculation_basis' => ['required', Rule::in(['contract_value', 'total_revenue'])],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['BRL'])],
            'commission_rate' => ['sometimes', 'numeric', 'between:0,100'],
            'commission_basis' => ['sometimes', Rule::in(['gross', 'net_of_taxes'])],
            'estimated_labor_cost' => ['sometimes', 'numeric', 'min:0'],
            'estimated_additional_cost' => ['sometimes', 'numeric', 'min:0'],
            'estimated_minutes' => ['sometimes', 'integer', 'min:0'],

            // Campos temporários da API legada.
            'client_name' => ['nullable', 'string', 'max:150'],
            'status' => [$versioned ? 'sometimes' : 'required', Rule::in(['planning', 'in_progress', 'review', 'completed', 'paused', 'cancelled'])],
            'progress' => [$versioned ? 'sometimes' : 'required', 'integer', 'between:0,100'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->is('api/v1/*')) {
                return;
            }
            foreach (ProjectFields::LABELS as $field => $label) {
                if ($this->has($field) && ! ProjectFields::allowed($this->user(), $field, 'edit')) {
                    $validator->errors()->add($field, 'Sem permissão para alterar: '.$label);
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
        if ($this->is('api/v1/*') && $this->isMethod('POST')) {
            $this->merge([
                'status' => $this->input('status', 'planning'),
                'progress' => $this->integer('progress', 0),
            ]);
        }
    }
}
