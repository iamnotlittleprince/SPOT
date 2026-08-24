<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\ProjectExpense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewProjectExpense
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ProjectExpense $expense, User $reviewer, string $decision, ?string $reason): ProjectExpense
    {
        return DB::transaction(function () use ($expense, $reviewer, $decision, $reason): ProjectExpense {
            $expense = ProjectExpense::query()->lockForUpdate()->findOrFail($expense->getKey());
            if ($expense->project()->whereNotNull('finalized_at')->exists()) {
                throw ValidationException::withMessages(['project' => 'Projeto finalizado não aceita alterações em despesas.']);
            }
            if ($expense->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'A despesa já foi analisada.']);
            }

            $expense->forceFill([
                'status' => $decision,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ])->save();
            $this->audit->record("expense.{$decision}", $expense, $reviewer, ['status' => 'pending'], ['status' => $decision, 'reason' => $reason]);

            return $expense->refresh();
        });
    }
}
