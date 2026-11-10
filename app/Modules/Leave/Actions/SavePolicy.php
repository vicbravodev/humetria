<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Support\StatutoryDays;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a vacation policy with its rows. A policy can only improve the law (criterio C-13): no tier under
 * the days of art. 76 for its last year, no premium under 25 percent (criterio C-08), no two tiers for the same year. The
 * database holds the same floor. Periods already granted keep the terms they were granted with (criterio C-15).
 */
final readonly class SavePolicy
{
    public const int PREMIUM_FLOOR = 25;

    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @param  array{name: string, premium_percent: int, work_week: string, allows_advance: bool, extra_expires_months: int|null, extra_carryover_cap_days: int|null, rows: list<array{kind: string, label?: string|null, year_from?: int|null, year_to?: int|null, days: int}>}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?string $policyId = null): LeavePolicy
    {
        $policy = DB::transaction(function () use ($data, $policyId): LeavePolicy {
            $policy = $policyId === null ? new LeavePolicy(['tenant_id' => $this->tenant->id()]) : LeavePolicy::query()->lockForUpdate()->findOrFail($policyId);
            $errors = $this->errors($data, $policy);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $policy->fill([
                'name' => $data['name'],
                'premium_percent' => $data['premium_percent'],
                'work_week' => $data['work_week'],
                'allows_advance' => $data['allows_advance'],
                'extra_expires_months' => $data['extra_expires_months'],
                'extra_carryover_cap_days' => $data['extra_carryover_cap_days'],
            ])->save();
            $policy->rows()->delete();

            foreach ($data['rows'] as $row) {
                $tier = RowKind::from($row['kind']) === RowKind::Tier;
                $policy->rows()->create([
                    'tenant_id' => $policy->tenant_id,
                    'kind' => $row['kind'],
                    'label' => $tier ? null : ($row['label'] ?? null),
                    'year_from' => $tier ? ($row['year_from'] ?? null) : null,
                    'year_to' => $tier ? ($row['year_to'] ?? null) : null,
                    'days' => $row['days'],
                ]);
            }

            $this->audit->handle('leave.policy_saved', $policy, ['rows' => count($data['rows']), 'premium_percent' => $policy->premium_percent]);

            return $policy;
        });

        Log::info('leave.policy_saved', ['policy_id' => $policy->id, 'rows' => count($data['rows'])]);

        return $policy;
    }

    /**
     * @param  array{name: string, premium_percent: int, rows: list<array{kind: string, label?: string|null, year_from?: int|null, year_to?: int|null, days: int}>}  $data
     * @return array<string, string>
     */
    private function errors(array $data, LeavePolicy $policy): array
    {
        $errors = [];

        if ($data['premium_percent'] < self::PREMIUM_FLOOR) {
            $errors['premium_percent'] = 'La prima no puede ser menor a '.self::PREMIUM_FLOOR.' %.';
        }

        if (LeavePolicy::query()->where('name', $data['name'])->whereKeyNot($policy->id)->exists()) {
            $errors['name'] = 'Ya hay una política con ese nombre.';
        }

        $tiers = [];

        foreach ($data['rows'] as $index => $row) {
            if (RowKind::from($row['kind']) === RowKind::Fixed) {
                $errors += $this->fixedErrors($index, $row['label'] ?? '', $row['days']);

                continue;
            }

            $from = $row['year_from'] ?? 0;
            $to = $row['year_to'] ?? 0;

            if ($from < 1 || $to < $from) {
                $errors["rows.{$index}.year_from"] = 'Los años van del 1 en adelante, de menor a mayor.';

                continue;
            }

            // The table only grows: the last year of the range is the one that asks for the most.
            $floor = StatutoryDays::current($to);

            if ($row['days'] < $floor) {
                $errors["rows.{$index}.days"] = "No puede ser menor a los {$floor} días que marca la ley.";
            }

            foreach ($tiers as [$otherFrom, $otherTo]) {
                if ($from <= $otherTo && $otherFrom <= $to) {
                    $errors["rows.{$index}.year_from"] = 'Se traslapa con otro renglón.';
                }
            }

            $tiers[] = [$from, $to];
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function fixedErrors(int $index, string $label, int $days): array
    {
        return [
            ...(trim($label) === '' ? ["rows.{$index}.label" => 'Ponle un nombre a estos días.'] : []),
            ...($days < 1 ? ["rows.{$index}.days" => 'Debe ser al menos 1 día.'] : []),
        ];
    }
}
