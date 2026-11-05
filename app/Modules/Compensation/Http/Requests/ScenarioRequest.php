<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Data\ScenarioData;
use App\Modules\Compensation\Data\TermChange;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\BenefitValue;
use App\Modules\Compensation\Support\PercentBasisPoints;
use App\Modules\Compensation\Support\TenureTable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A scenario as the editor sends it: raises and assumptions as percents, new terms as the benefit forms type them. */
final class ScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows('manage-compensation')) {
            return false;
        }

        // A scenario of another company is a 404 whatever the form says, before it is validated.
        $scenario = $this->route('scenario');

        if (is_string($scenario)) {
            Scenario::query()->findOrFail($scenario);
        }

        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isPreview() ? 'nullable' : 'required', 'string', 'max:255'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'retroactive_months' => ['required', 'integer'],
            'horizon_years' => ['required', 'integer'],
            'raise_unit' => ['nullable', 'in:'.implode(',', array_column(RaiseUnit::cases(), 'value'))],
            'unionized_raise' => ['required', 'string'],
            'trusted_raise' => ['nullable', 'string'],
            'default_state' => ['nullable', 'string', 'max:3'],
            'assumptions' => ['nullable', 'array'],
            'term_changes' => ['nullable', 'array', 'max:'.TenureTable::MAX_ROWS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_on' => 'vigencia', 'retroactive_months' => 'retroactividad', 'horizon_years' => 'horizonte',
            'raise_unit' => 'tipo de aumento', 'unionized_raise' => 'aumento de sindicalizados', 'trusted_raise' => 'aumento de confianza', 'default_state' => 'estado',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (['unionized_raise', 'trusted_raise'] as $field) {
                if ($this->filled($field) && $this->raise($field) === null) {
                    $validator->errors()->add($field, $this->unit() === RaiseUnit::Amount ? 'Escribe un monto al mes como 500 o 500.50.' : 'Escribe un porcentaje como 5 o 5.75.');
                }
            }

            foreach ($this->assumptionTexts() as $key => $text) {
                if (PercentBasisPoints::parse($text) === null) {
                    $validator->errors()->add("assumptions.{$key}", 'Escribe un porcentaje como 3 o 3.5.');
                }
            }

            if ($this->termChanges() === null) {
                $validator->errors()->add('term_changes', 'Cada prestación que cambias lleva su valor, como 12 o 12.5, y su tabla por antigüedad, años enteros y valor.');
            }
        }];
    }

    public function toData(?string $id = null): ScenarioData
    {
        $assumptions = [];

        foreach ($this->assumptionTexts() as $key => $text) {
            $assumptions[$key] = PercentBasisPoints::parse($text) ?? 0;
        }

        return new ScenarioData(
            $id,
            $this->string('name')->toString(),
            $this->date('effective_on', 'Y-m-d')?->toImmutable() ?? CarbonImmutable::now(),
            $this->integer('retroactive_months'),
            $this->integer('horizon_years'),
            $this->raise('unionized_raise'),
            $this->filled('trusted_raise') ? $this->raise('trusted_raise') : null,
            $this->termChanges() ?? [],
            $assumptions,
            $this->filled('default_state') ? $this->string('default_state')->toString() : null,
            $this->unit(),
        );
    }

    private function isPreview(): bool
    {
        return $this->routeIs('tenant.compensation.scenarios.preview');
    }

    private function unit(): RaiseUnit
    {
        return $this->enum('raise_unit', RaiseUnit::class) ?? RaiseUnit::Percent;
    }

    /** A raise as typed: a percent in basis points, or an amount a month in cents; null when it is neither. */
    private function raise(string $field): ?int
    {
        $text = $this->string($field)->toString();

        return $this->unit() === RaiseUnit::Amount ? BenefitValue::parse($text) : PercentBasisPoints::parse($text);
    }

    /** @return array<string, string> what was typed for each assumption, by key */
    private function assumptionTexts(): array
    {
        $texts = [];

        foreach ($this->array('assumptions') as $key => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $key] = $text;
            }
        }

        return $texts;
    }

    /**
     * The changes as numbers; null when a value is not a number like 12.5 or a row of a table is not a whole number of years and a value.
     *
     * @return ?list<TermChange>
     */
    private function termChanges(): ?array
    {
        $changes = [];

        foreach ($this->array('term_changes') as $row) {
            $id = is_array($row) && is_string($row['benefit_id'] ?? null) ? $row['benefit_id'] : '';
            $value = is_array($row) && is_string($row['value'] ?? null) ? BenefitValue::parse($row['value']) : null;
            $table = is_array($row) ? $this->table($row['tenure_table'] ?? []) : null;

            if ($id === '' || $value === null || $table === null) {
                return null;
            }

            $changes[] = new TermChange($id, $value, $table === [] ? null : $table);
        }

        return $changes;
    }

    /**
     * @return ?list<array{from_years: int, value: int}>
     */
    private function table(mixed $rows): ?array
    {
        $table = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $years = is_array($row) && is_string($row['from_years'] ?? null) ? $row['from_years'] : '';
            $value = is_array($row) && is_string($row['value'] ?? null) ? BenefitValue::parse($row['value']) : null;

            if (preg_match('/\A\d{1,3}\z/', $years) !== 1 || $value === null) {
                return null;
            }

            $table[] = ['from_years' => (int) $years, 'value' => $value];
        }

        return $table;
    }
}
