<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Exceptions;

use RuntimeException;

/**
 * Another request changed the campaign between reading it and locking it (a double click, two tabs): what was asked
 * no longer applies, so nothing is done. Answered as 409 Conflict.
 */
final class CampaignStateChanged extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The campaign changed meanwhile.');
    }

    public function render(): never
    {
        abort(409);
    }
}
