<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/** Where a module of a company comes from. */
enum ModuleSource: string
{
    /** The plan the company has. */
    case Plan = 'plan';
    /** What it had before a plan came: kept until the day the plan takes effect. */
    case Legacy = 'legacy';
    /** A person gave it, with a reason and maybe an end. */
    case Grant = 'grant';
    /** A person took it away, with a reason. */
    case Revoke = 'revoke';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Plan',
            self::Legacy => 'Se quita al empezar el plan',
            self::Grant => 'Concedido a mano',
            self::Revoke => 'Quitado a mano',
        };
    }
}
