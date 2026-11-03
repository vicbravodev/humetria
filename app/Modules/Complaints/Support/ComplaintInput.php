<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use Carbon\CarbonImmutable;

/** What a person wrote in the public form, already validated. */
final readonly class ComplaintInput
{
    public function __construct(
        public ComplaintType $type,
        public ?ComplaintCategory $category,
        public string $description,
        public ?CarbonImmutable $occurredOn,
        public ?string $workCenterId,
        public RespondentKind $respondentKind,
        public ?string $respondentText,
        public IdentityMode $identityMode,
        public ?string $reporterName = null,
        public ?string $reporterContact = null,
        public ?string $involvedText = null,
    ) {}
}
