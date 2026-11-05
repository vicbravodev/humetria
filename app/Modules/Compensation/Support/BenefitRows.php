<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A page of the catalog as the screen shows it: each benefit with its terms, the one in force on a date and whether it falls
 * below the law. This is the one place, besides the calculation, where Compensación sends money to a page.
 */
final readonly class BenefitRows
{
    public function __construct(private JobGrades $jobGrades, private FiscalParameters $fiscal) {}

    /**
     * @param  LengthAwarePaginator<int, Benefit>  $page  with its terms loaded
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function through(LengthAwarePaginator $page, CarbonImmutable $today): LengthAwarePaginator
    {
        $grades = $this->jobGrades->findMany(array_values(array_unique(array_merge(...$page->getCollection()->map(fn (Benefit $benefit): array => $benefit->job_grade_ids)->all()))));
        $legal = $this->fiscal->at($today);

        return $page->through(fn (Benefit $benefit): array => $this->row($benefit, $grades, $legal, $today->toDateString()));
    }

    /**
     * @param  array<string, JobGradeData>  $grades
     * @return array<string, mixed>
     */
    private function row(Benefit $benefit, array $grades, FiscalSnapshot $legal, string $today): array
    {
        $terms = $benefit->terms->map(fn (ContractTerm $term): array => $this->term($benefit, $term, $legal))->values()->all();
        $current = $benefit->terms->first(fn (ContractTerm $term): bool => $term->effective_from->toDateString() <= $today);

        return [
            'id' => $benefit->id,
            'name' => $benefit->name,
            'is_base' => $benefit->isBase(),
            'kind' => $benefit->kind->value,
            'kind_label' => $benefit->kind->label(),
            'unit' => $benefit->kind->unit(),
            'frequency' => $benefit->frequency->value,
            'frequency_label' => $benefit->frequency->label(),
            'group' => $benefit->applies_to_group->value ?? '',
            'group_label' => $benefit->applies_to_group?->label() ?? 'Todos',
            'grades' => array_map(
                fn (string $id): array => ['value' => $id, 'label' => "{$grades[$id]->category} · {$grades[$id]->level}"],
                array_values(array_filter($benefit->job_grade_ids, fn (string $id): bool => isset($grades[$id]))),
            ),
            'min_tenure_months' => $benefit->min_tenure_months,
            'sbc_integration' => $benefit->sbc_integration->value,
            'sbc_integration_label' => $benefit->sbc_integration === SbcIntegration::UpToCap && $benefit->sbc_cap_cents === null ? 'Hasta el tope de la ley' : $benefit->sbc_integration->label(),
            'sbc_cap' => $benefit->sbc_cap_cents === null ? '' : BenefitValue::decimal($benefit->sbc_cap_cents),
            'in_payroll_tax' => $benefit->in_payroll_tax,
            'in_ptu_base' => $benefit->in_ptu_base,
            'in_seniority_base' => $benefit->in_seniority_base,
            'income_tax_exempt' => $benefit->income_tax_exempt,
            'cap' => $benefit->cap_cents === null ? '' : BenefitValue::decimal($benefit->cap_cents),
            'archived' => $benefit->archived_at !== null,
            'current' => $current === null ? null : ['value_text' => BenefitValue::format($benefit->kind, $current->value), 'effective_from' => $current->effective_from->toDateString()],
            'warning' => $current === null ? null : LegalMinimum::problem($benefit->baseKey(), $current->value, $current->tenure_table, $legal),
            'terms' => $terms,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function term(Benefit $benefit, ContractTerm $term, FiscalSnapshot $legal): array
    {
        return [
            'id' => $term->id,
            'effective_from' => $term->effective_from->toDateString(),
            'value' => BenefitValue::decimal($term->value),
            'value_text' => BenefitValue::format($benefit->kind, $term->value),
            'tenure_table' => $term->tenure_table === null ? null : array_map(fn (array $row): array => [
                'from_years' => $row['from_years'],
                'value' => BenefitValue::decimal($row['value']),
                'value_text' => BenefitValue::format($benefit->kind, $row['value']),
            ], $term->tenure_table),
            'warning' => LegalMinimum::problem($benefit->baseKey(), $term->value, $term->tenure_table, $legal),
        ];
    }
}
