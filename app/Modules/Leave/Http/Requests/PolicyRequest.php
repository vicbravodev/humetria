<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Enums\WorkWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A vacation policy with its rows, written by owner and hr. Here the shape; the floors of the law (days by year, premium
 * of 25 percent) are held by SavePolicy, which the database repeats as a CHECK.
 */
final class PolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'premium_percent' => ['required', 'integer', 'between:0,100'],
            'work_week' => ['required', Rule::enum(WorkWeek::class)],
            'allows_advance' => ['nullable', 'boolean'],
            'extra_expires_months' => ['nullable', 'integer', 'between:1,120'],
            'extra_carryover_cap_days' => ['nullable', 'integer', 'between:0,365'],
            'rows' => ['present', 'array', 'max:40'],
            'rows.*.kind' => ['required', Rule::enum(RowKind::class)],
            'rows.*.label' => ['nullable', 'string', 'max:80'],
            'rows.*.year_from' => ['nullable', 'integer', 'between:1,100'],
            'rows.*.year_to' => ['nullable', 'integer', 'between:1,100'],
            'rows.*.days' => ['required', 'integer', 'between:0,365'],
        ];
    }

    /**
     * What SavePolicy takes.
     *
     * @return array{name: string, premium_percent: int, work_week: string, allows_advance: bool, extra_expires_months: int|null, extra_carryover_cap_days: int|null, rows: list<array{kind: string, label: string|null, year_from: int|null, year_to: int|null, days: int}>}
     */
    public function policy(): array
    {
        $rows = [];

        foreach (array_keys($this->array('rows')) as $index) {
            $rows[] = [
                'kind' => $this->string("rows.{$index}.kind")->toString(),
                'label' => $this->filled("rows.{$index}.label") ? $this->string("rows.{$index}.label")->toString() : null,
                'year_from' => $this->nullableInt("rows.{$index}.year_from"),
                'year_to' => $this->nullableInt("rows.{$index}.year_to"),
                'days' => $this->integer("rows.{$index}.days"),
            ];
        }

        return [
            'name' => trim($this->string('name')->toString()),
            'premium_percent' => $this->integer('premium_percent'),
            'work_week' => $this->string('work_week')->toString(),
            'allows_advance' => $this->boolean('allows_advance'),
            'extra_expires_months' => $this->nullableInt('extra_expires_months'),
            'extra_carryover_cap_days' => $this->nullableInt('extra_carryover_cap_days'),
            'rows' => $rows,
        ];
    }

    private function nullableInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
