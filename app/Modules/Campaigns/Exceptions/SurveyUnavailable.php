<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Exceptions;

use App\Modules\Campaigns\Enums\SurveyUnavailableReason;
use RuntimeException;

final class SurveyUnavailable extends RuntimeException
{
    public function __construct(public readonly SurveyUnavailableReason $reason, public readonly ?string $company = null)
    {
        parent::__construct("Survey unavailable: {$reason->value}.");
    }
}
