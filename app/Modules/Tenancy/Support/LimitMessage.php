<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CompanyLimits;

/** What a person is told when the plan stops them: what it covers, how many are left, what to do. */
final class LimitMessage
{
    /**
     * @param  string|null  $step  what the next size would cost, from Billing.
     */
    public static function refusal(string $resource, int $amount, int $used, int $quantity, ?string $step): string
    {
        $seats = $resource === CompanyLimits::SEATS;
        $left = max(0, $amount - $used);
        $covers = 'Tu plan cubre '.self::count($seats, $amount);

        $message = $left > 0 && $quantity > $left
            ? "{$covers} y ".($left === 1 ? 'te queda 1 lugar' : "te quedan {$left} lugares")."; esto agrega {$quantity}. No se agregó a nadie. Pide una ampliación a Humetria."
            : "{$covers} y ".self::all($seats, $amount).' Pide una ampliación a Humetria para '.($seats ? 'dar de alta a más.' : 'agregar otro.');

        return $step === null ? $message : "{$message} {$step}";
    }

    private static function count(bool $seats, int $amount): string
    {
        return match (true) {
            $seats => $amount === 1 ? '1 persona' : "{$amount} personas",
            default => $amount === 1 ? '1 centro de trabajo' : "{$amount} centros de trabajo",
        };
    }

    private static function all(bool $seats, int $amount): string
    {
        return match (true) {
            $seats => $amount === 1 ? 'ya la tienes.' : 'ya las tienes todas.',
            default => $amount === 1 ? 'ya lo tienes.' : 'ya los tienes todos.',
        };
    }
}
