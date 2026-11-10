<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/**
 * What a load by CSV usually leaves wrong in the reporting line, each with the people to fix.
 */
enum ReportingIssue: string
{
    /** Active, with no manager and nobody who reports to them. */
    case WithoutManager = 'without_manager';
    /** Active, with no manager and at least one report: the issue is having more than one. */
    case ExtraRoot = 'roots';
    /** Active, under a manager who left. */
    case InactiveManager = 'inactive_manager';

    public function label(): string
    {
        return match ($this) {
            self::WithoutManager => 'Sin jefe',
            self::ExtraRoot => 'Raíces',
            self::InactiveManager => 'Jefe dado de baja',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::WithoutManager => 'Personas activas sin jefe directo y sin nadie que les reporte.',
            self::ExtraRoot => 'Personas sin jefe directo a quienes alguien les reporta. Normalmente solo hay una.',
            self::InactiveManager => 'Personas activas cuyo jefe directo fue dado de baja.',
        };
    }
}
