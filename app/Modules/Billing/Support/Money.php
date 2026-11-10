<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

/** Cents written as pesos for people to read: "$4,900", "$149.50". Only for text; every sum is done in cents. */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        $pesos = number_format(intdiv($absolute, 100));
        $rest = $absolute % 100;

        return $sign.'$'.$pesos.($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }
}
