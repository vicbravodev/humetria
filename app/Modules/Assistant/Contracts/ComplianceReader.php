<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

use App\Modules\Assistant\Data\ComplianceStatus;

/**
 * Where the current company stands with one norm or module (NOM-035, NOM-037, Ley Silla…), as Humi tells it. A new norm
 * is one more reader in `ComplianceReaders`: the tool that lists them does not change.
 */
interface ComplianceReader
{
    public function status(): ComplianceStatus;
}
