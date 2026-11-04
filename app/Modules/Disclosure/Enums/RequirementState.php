<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

/**
 * Where a requirement of the board stands in a work center: what is recorded, never a verdict of the STPS. «Cumple» is the
 * requirement recorded, «Con aviso» is recorded with something to look at, «Falta» is not recorded, and «No aplica» is what
 * the norm does not ask of that center.
 */
enum RequirementState: string
{
    case Met = 'met';
    case Notice = 'notice';
    case Missing = 'missing';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'Cumple',
            self::Notice => 'Con aviso',
            self::Missing => 'Falta',
            self::NotApplicable => 'No aplica',
        };
    }
}
