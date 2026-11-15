<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/**
 * Why a person's work relationship ended. A closed list: HR picks one, nobody types a reason.
 */
enum DeactivationReason: string
{
    case Resignation = 'resignation';
    case Dismissal = 'dismissal';
    case ContractEnd = 'contract_end';
    case MutualAgreement = 'mutual_agreement';
    case Death = 'death';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Resignation => 'Renuncia',
            self::Dismissal => 'Rescisión o despido',
            self::ContractEnd => 'Término de contrato',
            self::MutualAgreement => 'Mutuo acuerdo',
            self::Death => 'Fallecimiento',
            self::Other => 'Otro',
        };
    }
}
