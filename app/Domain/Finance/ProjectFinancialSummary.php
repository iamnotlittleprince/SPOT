<?php

namespace App\Domain\Finance;

use App\Models\Project;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class ProjectFinancialSummary
{
    /** @return array<string, mixed> */
    public function calculate(Project $project): array
    {
        $project->loadMissing(['taxes.type', 'workLogs', 'expenses']);
        $contract = $this->money($project->contract_value);
        $overtimeRevenue = $this->sumWorkLogs($project, 'sale_rate_snapshot', overtimeOnly: true);
        $revenue = $contract->plus($overtimeRevenue);
        [$taxes, $taxComposition] = $this->taxes($project, $revenue);
        $commission = $this->commission($project, $revenue, $taxes);
        $labor = $this->sumWorkLogs($project, 'cost_rate_snapshot');
        $expenses = $project->expenses->where('status', 'approved')->reduce(
            fn (BigDecimal $sum, $expense) => $sum->plus($this->money($expense->amount)),
            BigDecimal::zero(),
        );
        $operatingCosts = $labor->plus($expenses);
        $totalDeductions = $taxes->plus($commission)->plus($operatingCosts);
        $profit = $revenue->minus($totalDeductions);

        [$estimatedTaxes] = $this->taxes($project, $contract);
        $estimatedCommission = $this->commission($project, $contract, $estimatedTaxes);
        $estimatedCosts = $this->money($project->estimated_labor_cost)->plus($this->money($project->estimated_additional_cost));
        $estimatedProfit = $contract->minus($estimatedTaxes)->minus($estimatedCommission)->minus($estimatedCosts);
        $workedMinutes = (int) $project->workLogs->where('status', '!=', 'rejected')->sum('duration_minutes');

        return [
            'project_id' => $project->id,
            'currency' => $project->currency,
            'actual' => [
                'contract_revenue' => $this->format($contract),
                'overtime_revenue' => $this->format($overtimeRevenue),
                'total_revenue' => $this->format($revenue),
                'taxes' => $this->format($taxes),
                'tax_composition' => $taxComposition,
                'commission' => $this->format($commission),
                'labor_cost' => $this->format($labor),
                'approved_expenses' => $this->format($expenses),
                'operating_costs' => $this->format($operatingCosts),
                'total_deductions' => $this->format($totalDeductions),
                'profit' => $this->format($profit),
                'margin_percent' => $this->percentage($profit, $revenue),
                'worked_minutes' => $workedMinutes,
                'estimated_minutes_consumed_percent' => $project->estimated_minutes > 0
                    ? round(($workedMinutes / $project->estimated_minutes) * 100, 2) : null,
            ],
            'estimated' => [
                'revenue' => $this->format($contract),
                'taxes' => $this->format($estimatedTaxes),
                'commission' => $this->format($estimatedCommission),
                'labor_cost' => $this->format($this->money($project->estimated_labor_cost)),
                'additional_cost' => $this->format($this->money($project->estimated_additional_cost)),
                'profit' => $this->format($estimatedProfit),
                'margin_percent' => $this->percentage($estimatedProfit, $contract),
                'minutes' => $project->estimated_minutes,
            ],
        ];
    }

    /** @return array{BigDecimal, array<int, array<string, string>>} */
    private function taxes(Project $project, BigDecimal $revenue): array
    {
        $composition = [];
        $total = BigDecimal::zero();
        foreach ($project->taxes as $tax) {
            $basis = $tax->calculation_basis === 'total_revenue' ? $revenue : $this->money($project->contract_value);
            $amount = $tax->calculation_type === 'fixed'
                ? $this->money($tax->fixed_amount)
                : $basis->multipliedBy($this->money($tax->rate))->dividedBy(100, 2, RoundingMode::HalfUp);
            $total = $total->plus($amount);
            $composition[] = ['name' => $tax->type?->name ?? 'Imposto', 'amount' => $this->format($amount)];
        }

        return [$total, $composition];
    }

    private function commission(Project $project, BigDecimal $revenue, BigDecimal $taxes): BigDecimal
    {
        $basis = $project->commission_basis === 'net_of_taxes' ? $revenue->minus($taxes) : $revenue;
        return $basis->multipliedBy($this->money($project->commission_rate))->dividedBy(100, 2, RoundingMode::HalfUp);
    }

    private function sumWorkLogs(Project $project, string $rateField, bool $overtimeOnly = false): BigDecimal
    {
        return $project->workLogs->where('status', '!=', 'rejected')
            ->when($overtimeOnly, fn ($logs) => $logs->where('is_overtime', true))
            ->reduce(fn (BigDecimal $sum, $log) => $sum->plus(
                $this->money($log->{$rateField})->multipliedBy($log->duration_minutes)->dividedBy(60, 2, RoundingMode::HalfUp)
            ), BigDecimal::zero());
    }

    private function money(mixed $value): BigDecimal { return BigDecimal::of($value ?? 0); }
    private function format(BigDecimal $value): string { return (string) $value->toScale(2, RoundingMode::HalfUp); }
    private function percentage(BigDecimal $part, BigDecimal $total): ?string
    {
        if ($total->isZero()) return null;
        return (string) $part->multipliedBy(100)->dividedBy($total, 2, RoundingMode::HalfUp);
    }
}
