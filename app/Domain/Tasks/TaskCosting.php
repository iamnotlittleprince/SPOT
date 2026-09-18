<?php

namespace App\Domain\Tasks;

use App\Models\AnalystProjectRate;
use App\Models\Task;

final class TaskCosting
{
    public function capture(Task $task): void
    {
        $rate = AnalystProjectRate::where('project_id', $task->project_id)->where('user_id', $task->user_id)
            ->whereDate('effective_from', '<=', $task->worked_on)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $task->worked_on))->latest('effective_from')->first();
        $task->forceFill(['cost_rate_snapshot' => $rate ? ($task->is_overtime ? $rate->overtime_cost_rate : $rate->normal_cost_rate) : null,
            'sale_rate_snapshot' => $rate ? ($task->is_overtime ? $rate->overtime_sale_rate : 0) : null])->save();
    }
}
