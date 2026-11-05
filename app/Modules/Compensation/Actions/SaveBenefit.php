<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Data\BenefitData;
use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveBenefit
{
    private const int NAME_LENGTH = 120;

    public function __construct(private CurrentTenant $currentTenant, private JobGrades $jobGrades, private SaveContractTerm $saveTerm) {}

    /**
     * Creates a benefit the company defines by its attributes, with its first term when one is given, or edits one it already
     * made up (its terms stay). A base benefit is not edited here: only its terms are.
     *
     * @throws ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the benefit is another company's
     */
    public function handle(BenefitData $data, ?TermData $term = null): Benefit
    {
        $benefit = $data->id === null ? null : Benefit::query()->findOrFail($data->id);

        if ($benefit?->isBase()) {
            throw ValidationException::withMessages(['benefit' => 'Las prestaciones base no se editan: cambia sus términos.']);
        }

        $name = trim($data->name);
        $grades = array_values(array_unique($data->jobGradeIds));
        $errors = [
            ...$this->nameProblem($name, $benefit),
            ...$this->capProblems($data),
            ...$this->gradeProblem($grades),
            ...($data->minTenureMonths < 0 ? ['min_tenure_months' => 'La antigüedad mínima no puede ser negativa.'] : []),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($benefit, $data, $term): Benefit {
            $attributes = $this->attributes($data);

            $saved = $benefit ?? new Benefit(['tenant_id' => $this->currentTenant->id()]);
            $saved->fill($attributes)->save();

            // Ids only: a cap is money, which stays out of logs.
            Log::info('compensation.benefit_saved', ['benefit_id' => $saved->id, 'created' => $benefit === null]);

            if ($term !== null) {
                $this->saveTerm->handle($saved, $term);
            }

            return $saved;
        });
    }

    /**
     * The columns of a benefit the company defines: what its cost depends on.
     *
     * @return array<model-property<Benefit>, mixed>
     */
    public function attributes(BenefitData $data): array
    {
        return [
            'name' => trim($data->name),
            'kind' => $data->kind,
            'frequency' => $data->frequency,
            'applies_to_group' => $data->group,
            'job_grade_ids' => array_values(array_unique($data->jobGradeIds)),
            'min_tenure_months' => $data->minTenureMonths,
            'sbc_integration' => $data->sbcIntegration,
            'sbc_cap_cents' => $data->sbcIntegration === SbcIntegration::UpToCap ? $data->sbcCapCents : null,
            'in_payroll_tax' => $data->inPayrollTax,
            'in_ptu_base' => $data->inPtuBase,
            'in_seniority_base' => $data->inSeniorityBase,
            'income_tax_exempt' => $data->incomeTaxExempt,
            'cap_cents' => $data->capCents,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nameProblem(string $name, ?Benefit $benefit): array
    {
        if ($name === '') {
            return ['name' => 'Ponle un nombre a la prestación.'];
        }

        if (mb_strlen($name) > self::NAME_LENGTH) {
            return ['name' => 'El nombre puede tener '.self::NAME_LENGTH.' caracteres a lo más.'];
        }

        $taken = Benefit::query()
            ->whereRaw('lower(btrim(name)) = lower(?)', [$name])
            ->when($benefit !== null, fn ($query) => $query->whereKeyNot($benefit?->id))
            ->exists();

        return $taken ? ['name' => 'Ya hay una prestación con este nombre.'] : [];
    }

    /**
     * @return array<string, string>
     */
    private function capProblems(BenefitData $data): array
    {
        $errors = [];

        if ($data->capCents !== null && $data->capCents < 0) {
            $errors['cap'] = 'El tope no puede ser negativo.';
        }

        if ($data->sbcIntegration === SbcIntegration::UpToCap && ($data->sbcCapCents === null || $data->sbcCapCents < 0)) {
            $errors['sbc_cap'] = 'Indica hasta cuánto integra al salario base de cotización, de cero en adelante.';
        }

        return $errors;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function gradeProblem(array $ids): array
    {
        $valid = array_filter($ids, Str::isUuid(...));

        if (count($valid) !== count($ids) || count($this->jobGrades->findMany(array_values($valid))) !== count($ids)) {
            return ['job_grade_ids' => 'Elige categorías del tabulador de tu cuenta.'];
        }

        return [];
    }
}
