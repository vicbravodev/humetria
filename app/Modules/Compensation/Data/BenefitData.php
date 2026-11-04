<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;

/**
 * What defines a benefit the company makes up (the attributes of the form), without its money: that is in the terms. Caps
 * are in cents.
 */
final readonly class BenefitData
{
    /**
     * @param  ?string  $id  null to create one
     * @param  list<string>  $jobGradeIds  empty = every category
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public BenefitKind $kind,
        public Frequency $frequency,
        public ?PayGroup $group,
        public array $jobGradeIds,
        public int $minTenureMonths,
        public SbcIntegration $sbcIntegration,
        public ?int $sbcCapCents,
        public bool $inPayrollTax,
        public bool $inPtuBase,
        public bool $inSeniorityBase,
        public bool $incomeTaxExempt,
        public ?int $capCents,
    ) {}
}
