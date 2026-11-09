<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

/**
 * An email address as the audit trail and the log may show it: the first letter of the name and of the domain, and the
 *
 * domain's ending ("juan@empresa.com" → "j***@e***.com"). Enough to see that a link went to a different address than
 * before, never the address itself.
 */
final class MaskedEmail
{
    public static function of(string $email): string
    {
        $parts = explode('@', mb_strtolower(trim($email)));
        $labels = explode('.', $parts[1] ?? '');
        $ending = end($labels);

        if (count($parts) !== 2 || $parts[0] === '' || count($labels) < 2 || $labels[0] === '' || $ending === '') {
            return '***';
        }

        return mb_substr($parts[0], 0, 1).'***@'.mb_substr($labels[0], 0, 1).'***.'.$ending;
    }
}
