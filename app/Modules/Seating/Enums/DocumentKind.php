<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The documents RH keeps for the inspection: the internal regulation, the Comisión's minutes where the assessments were recorded, the diagnosis and program of safety and health where Figura 1 was integrated, and others.
 */
enum DocumentKind: string
{
    case Regulation = 'regulation';
    case CommissionMinutes = 'commission_minutes';
    case DiagnosisProgram = 'diagnosis_program';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Regulation => 'Reglamento interior',
            self::CommissionMinutes => 'Acta de la Comisión',
            self::DiagnosisProgram => 'Diagnóstico y programa de seguridad y salud',
            self::Other => 'Otro documento',
        };
    }
}
