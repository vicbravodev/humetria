<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What was corrected in the marks of some people (criterio 5.4 and 6.1), for the report for the authority: every
 * correction with the mark it points to, read in one query whatever their number. Each change in plain Spanish with local
 * times: «hora corregida de 08:12 a 08:00 (por RH): <motivo>», one line per step of a chain, «marca agregada (fin 17:00, …)»
 * and «marca anulada (fin 18:05, …)». Who changed it by role only: «a petición de la persona» when a request of theirs
 * produced it, «por RH» otherwise. Never a name.
 */
final readonly class CorrectionHistory
{
    private const array TYPES = [
        'start' => 'inicio',
        'break_start' => 'inicio de pausa',
        'break_end' => 'fin de pausa',
        'end' => 'fin',
    ];

    /**
     * @param  array<string, array{employee: string, type: string, at: CarbonImmutable|null, reason: string, corrects: string|null, originalAt: CarbonImmutable|null, originalType: string|null, originalCenter: string|null, requested: bool}>  $corrections  by id
     */
    private function __construct(private array $corrections, private string $timezone) {}

    /**
     * The corrections of these people whose time, or the time of the mark they void, falls between two instants. All of
     * this module's tables, each bounded by the company.
     *
     * @param  list<string>  $employeeIds
     */
    public static function of(array $employeeIds, CarbonImmutable $since, CarbonImmutable $until): self
    {
        $tenant = resolve(CurrentTenant::class)->id();
        $rows = DB::select(
            'select c.id, c.employee_id, c.type, c.marked_at, c.reason, c.corrects_mark_id,
                o.marked_at as original_at, o.type as original_type, o.work_center_id as original_center,
                exists (select 1 from "attendance_correction_requests" as r where r.tenant_id = ? and r.resulting_mark_id = c.id) as requested
            from "attendance_marks" as c
            left join "attendance_marks" as o on o.tenant_id = ? and o.id = c.corrects_mark_id
            where c.tenant_id = ? and c.method = ? and c.employee_id = any(?::uuid[])
                and coalesce(c.marked_at, o.marked_at) >= ? and coalesce(c.marked_at, o.marked_at) < ?',
            [
                $tenant, $tenant, $tenant, MarkMethod::Correction->value,
                '{'.implode(',', array_filter($employeeIds, Str::isUuid(...))).'}',
                $since->utc()->format('Y-m-d H:i:s.uP'), $until->utc()->format('Y-m-d H:i:s.uP'),
            ],
        );

        $corrections = [];
        foreach ($rows as $row) {
            $value = fn (string $key): mixed => is_object($row) && property_exists($row, $key) ? $row->{$key} : null;
            $text = fn (string $key): ?string => is_string($value($key)) ? $value($key) : null;
            $time = fn (string $key): ?CarbonImmutable => is_string($value($key)) ? CarbonImmutable::parse($value($key)) : null;
            $id = $text('id');

            if ($id !== null) {
                $corrections[$id] = [
                    'employee' => $text('employee_id') ?? '',
                    'type' => $text('type') ?? '',
                    'at' => $time('marked_at'),
                    'reason' => $text('reason') ?? '',
                    'corrects' => $text('corrects_mark_id'),
                    'originalAt' => $time('original_at'),
                    'originalType' => $text('original_type'),
                    'originalCenter' => $text('original_center'),
                    'requested' => $value('requested') === true,
                ];
            }
        }

        return new self($corrections, config()->string('app.display_timezone'));
    }

    /**
     * What the corrections among a day's marks changed, each step of a chain in order, with the instant to sort by.
     *
     * @param  list<AttendanceMark>  $marks
     * @return list<array{int, string}>
     */
    public function notesFor(array $marks): array
    {
        $notes = [];
        foreach ($marks as $mark) {
            if ($mark->method === MarkMethod::Correction) {
                foreach ($this->chain($mark->id) as $step) {
                    $notes[] = [$mark->marked_at?->getTimestamp() ?? 0, $step];
                }
            }
        }

        return $notes;
    }

    /**
     * A person's voided marks: when the original was, what it was, its center and the line that says so.
     *
     * @return list<array{at: CarbonImmutable, type: string, center: string, text: string}>
     */
    public function voidsOf(string $employeeId): array
    {
        $voids = [];
        foreach ($this->corrections as $correction) {
            if ($correction['employee'] === $employeeId && $correction['at'] === null && $correction['originalAt'] !== null) {
                $type = $correction['originalType'] ?? '';
                $voids[] = [
                    'at' => $correction['originalAt'],
                    'type' => $type,
                    'center' => $correction['originalCenter'] ?? '',
                    'text' => 'marca anulada ('.(self::TYPES[$type] ?? $type).' '.$this->local($correction['originalAt']).', '.self::author($correction['requested']).'): '.$correction['reason'],
                ];
            }
        }

        return $voids;
    }

    /** Whether a voided mark began its own day (a start), not part of another. */
    public static function beginsDay(string $type): bool
    {
        return $type === MarkType::Start->value;
    }

    /**
     * The steps that led to an effective correction, first to last.
     *
     * @return list<string>
     */
    private function chain(string $id): array
    {
        $steps = [];
        $seen = [];

        while (($correction = $this->corrections[$id] ?? null) !== null && ! isset($seen[$id])) {
            $seen[$id] = true;
            $author = self::author($correction['requested']);
            $at = $correction['at'] === null ? '' : $this->local($correction['at']);

            if ($correction['corrects'] === null || $correction['originalAt'] === null) {
                array_unshift($steps, 'marca agregada ('.(self::TYPES[$correction['type']] ?? $correction['type'])." {$at}, {$author}): {$correction['reason']}");

                break;
            }

            array_unshift($steps, "hora corregida de {$this->local($correction['originalAt'])} a {$at} ({$author}): {$correction['reason']}");
            $id = $correction['corrects'];
        }

        return $steps;
    }

    private static function author(bool $requested): string
    {
        return $requested ? 'a petición de la persona' : 'por RH';
    }

    private function local(CarbonImmutable $at): string
    {
        return $at->setTimezone($this->timezone)->format('H:i');
    }
}
