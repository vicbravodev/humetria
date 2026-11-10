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

    /** What the list holds, said to whoever will fix it. One person at the top is how it should be. */
    public function hint(int $count): string
    {
        return match (true) {
            $this === self::WithoutManager => 'Personas activas sin jefe directo y sin nadie que les reporte.',
            $this === self::InactiveManager => 'Personas activas cuyo jefe directo fue dado de baja.',
            $count === 0 => 'Nadie tiene a alguien que le reporte todavía.',
            $count === 1 => 'Una sola raíz: así debe ser.',
            default => "Hay {$count} personas hasta arriba. Normalmente solo hay una.",
        };
    }

    /** Whether that many people are something to fix: any, except at the top, where one is expected. */
    public function pending(int $count): bool
    {
        return $count > ($this === self::ExtraRoot ? 1 : 0);
    }
}
