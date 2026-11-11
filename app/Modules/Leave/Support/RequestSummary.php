<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;

/**
 * What an email may say of a request: vacations or «a leave» (never which one, criterio C-22), its dates and its
 * working days. No note, no reason and no amounts.
 */
final readonly class RequestSummary
{
    public function __construct(public bool $vacations, public CarbonImmutable $startsOn, public CarbonImmutable $endsOn, public int $workingDays, public ?bool $hadBalance) {}

    public static function of(LeaveRequest $request): self
    {
        return new self($request->leave_type_id === null, $request->starts_on, $request->ends_on, $request->working_days, $request->had_balance);
    }

    /** «vacaciones» or «un permiso». */
    public function what(): string
    {
        return $this->vacations ? 'vacaciones' : 'un permiso';
    }

    /** «del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables». */
    public function span(): string
    {
        $day = fn (CarbonImmutable $date): string => $date->isoFormat('D [de] MMMM [de] YYYY');

        return "del {$day($this->startsOn)} al {$day($this->endsOn)}: {$this->workingDays} ".($this->workingDays === 1 ? 'día laborable' : 'días laborables');
    }
}
