<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Contracts\DomainOwnership;

final class DnsDomainOwnership implements DomainOwnership
{
    public function proves(string $domain, string $token): bool
    {
        $records = @dns_get_record("_humetria-sso.{$domain}", DNS_TXT);

        foreach ($records === false ? [] : $records as $record) {
            if (($record['txt'] ?? null) === "humetria-sso-verification={$token}") {
                return true;
            }
        }

        return false;
    }
}
