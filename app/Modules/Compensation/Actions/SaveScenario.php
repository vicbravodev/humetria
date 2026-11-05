<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Data\ScenarioData;
use App\Modules\Compensation\Data\TermChange;
use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Projection;
use App\Modules\Compensation\Support\TenureTable;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class SaveScenario
{
    private const int NAME_LENGTH = 120;

    /** The first date the platform has fiscal values for (the lower end of the calculator's horizon). */
    private const string EARLIEST = '2023-01-01';

    private const int MAX_RAISE_BP = 10_000;

    private const int MAX_YEARS_AHEAD = 10;

    /** The range of each assumption, in basis points. */
    private const array RANGES = [
        'inflation' => [0, 5_000],
        'turnover' => [0, 10_000],
        'headcount_growth' => [-5_000, 5_000],
        'future_revisions' => [0, 5_000],
        'following_years_rate' => [0, 5_000],
    ];

    public function __construct(private CurrentTenant $currentTenant, private WorkCenters $workCenters) {}

    /**
     * Creates a scenario or edits one of the company. The assumptions are kept whole with their source and the day they were
     * set, so a later change of the platform's proposal does not move an old scenario.
     *
     * @throws ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the scenario is another company's
     */
    public function handle(ScenarioData $data): Scenario
    {
        $scenario = $data->id === null ? null : Scenario::query()->findOrFail($data->id);
        $name = trim($data->name);
        $errors = [
            ...$this->nameProblem($name),
            ...$this->periodProblems($data),
            ...$this->raiseProblems($data),
            ...$this->assumptionProblems($data),
            ...$this->termProblems($data),
            ...($data->defaultState !== null && ! array_key_exists($data->defaultState, $this->workCenters->states()) ? ['default_state' => 'Elige un estado de la lista.'] : []),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($scenario, $data, $name): Scenario {
            $saved = $scenario ?? new Scenario(['tenant_id' => $this->currentTenant->id()]);
            $saved->fill([
                'name' => $name,
                'effective_on' => $data->effectiveOn,
                'retroactive_months' => $data->retroactiveMonths,
                'horizon_years' => $data->horizonYears,
                'group_changes' => ['unionized' => $data->unionizedRaiseBp, 'trusted' => $data->trustedRaiseBp],
                'term_changes' => array_map(fn (TermChange $change): array => ['benefit_id' => $change->benefitId, 'value' => $change->value, 'tenure_table' => $change->tenureTable], $data->termChanges),
                'assumptions' => $this->assumptions($data, $scenario),
                'default_state' => $data->defaultState,
            ])->save();

            // Ids only: a raise or a new term is money, which stays out of logs.
            Log::info('compensation.scenario_saved', ['scenario_id' => $saved->id, 'created' => $scenario === null]);

            return $saved;
        });
    }

    /**
     * Every assumption, whole: the platform's value, source and date unless the person typed another; a value the scenario
     * already had keeps the source and date it had.
     *
     * @return array<string, array{value_bp: int, source: string, set_on: string}>
     */
    private function assumptions(ScenarioData $data, ?Scenario $scenario): array
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
        $defaults = AssumptionKey::defaultSnapshot();
        $assumptions = [];

        foreach (AssumptionKey::cases() as $key) {
            $value = $data->assumptions[$key->value] ?? $key->defaultBasisPoints();
            $kept = $scenario?->assumptions[$key->value] ?? null;

            $assumptions[$key->value] = match (true) {
                $kept !== null && $kept['value_bp'] === $value => $kept,
                $value === $key->defaultBasisPoints() => $defaults[$key->value],
                default => ['value_bp' => $value, 'source' => 'Capturado en este escenario.', 'set_on' => $today],
            };
        }

        return $assumptions;
    }

    /** @return array<string, string> */
    private function nameProblem(string $name): array
    {
        return match (true) {
            $name === '' => ['name' => 'Ponle un nombre al escenario.'],
            mb_strlen($name) > self::NAME_LENGTH => ['name' => 'El nombre puede tener '.self::NAME_LENGTH.' caracteres a lo más.'],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function periodProblems(ScenarioData $data): array
    {
        $errors = [];

        if ($data->horizonYears < Projection::MIN_HORIZON || $data->horizonYears > Projection::MAX_HORIZON) {
            $errors['horizon_years'] = 'El horizonte es de '.Projection::MIN_HORIZON.' a '.Projection::MAX_HORIZON.' años.';
        }

        if ($data->retroactiveMonths < 0 || $data->retroactiveMonths > $data->horizonYears * 12) {
            $errors['retroactive_months'] = 'La retroactividad va de 0 meses a los del horizonte ('.($data->horizonYears * 12).').';
        }

        $firstTerm = ContractTerm::query()->min('effective_from');

        if ($data->effectiveOn->toDateString() < self::EARLIEST || $data->effectiveOn->isAfter(CarbonImmutable::now()->addYears(self::MAX_YEARS_AHEAD))) {
            $errors['effective_on'] = 'La plataforma tiene los valores fiscales de enero de 2023 a '.self::MAX_YEARS_AHEAD.' años adelante: elige una vigencia dentro de ese tiempo.';
        } elseif (is_string($firstTerm) && $data->effectiveOn->toDateString() < $firstTerm) {
            $errors['effective_on'] = 'La vigencia no puede ser anterior a los primeros términos del contrato ('.CarbonImmutable::parse($firstTerm)->format('d/m/Y').').';
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function raiseProblems(ScenarioData $data): array
    {
        $errors = [];

        foreach (['unionized_raise' => $data->unionizedRaiseBp, 'trusted_raise' => $data->trustedRaiseBp] as $field => $raise) {
            if ($raise !== null && ($raise < 0 || $raise > self::MAX_RAISE_BP)) {
                $errors[$field] = 'El aumento va de 0 % a 100 %.';
            }
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function assumptionProblems(ScenarioData $data): array
    {
        $errors = [];

        foreach ($data->assumptions as $key => $value) {
            if (! isset(self::RANGES[$key])) {
                return ['assumptions' => 'Ese supuesto no existe.'];
            }

            [$min, $max] = self::RANGES[$key];

            if ($value < $min || $value > $max) {
                $errors["assumptions.{$key}"] = 'Va de '.($min / 100).' % a '.($max / 100).' %.';
            }
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function termProblems(ScenarioData $data): array
    {
        $ids = array_map(fn (TermChange $change): string => $change->benefitId, $data->termChanges);
        $usable = Benefit::query()->whereIn('id', $ids)->whereNull('archived_at')->count();

        if (count($ids) !== count(array_unique($ids)) || $usable !== count(array_unique($ids))) {
            return ['term_changes' => 'Cada prestación se cambia una sola vez, y tiene que ser una de las activas de tu cuenta.'];
        }

        foreach ($data->termChanges as $change) {
            if ($change->value < 0 || TenureTable::problem($change->tenureTable ?? []) !== null) {
                return ['term_changes' => 'Los valores no pueden ser negativos, y la tabla por antigüedad va de menos a más años, sin repetir uno.'];
            }
        }

        return [];
    }
}
