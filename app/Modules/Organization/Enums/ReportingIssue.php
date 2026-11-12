<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/**
 * The views of the list of the reporting line: everybody, or what a load by CSV usually leaves wrong, each with the
 * people to fix.
 */
enum ReportingIssue: string
{
    /** Every active person: the list as it is, the ones that need something first. */
    case All = 'all';
    /** Active, with no manager, whether they lead people or not. One at the top is how it should be. */
    case NoManager = 'no_manager';
    /** Active, with more direct reports than a manager can lead well (SpanOfControl::MANY_MIN or more). */
    case ManyReports = 'many_reports';
    /** Active, with no manager and nobody who reports to them. */
    case WithoutManager = 'without_manager';
    /** Active, under a manager who left. */
    case InactiveManager = 'inactive_manager';

    /**
     * The views of the list, in the order they are drawn: the ones that ask for action are moved first by the screen.
     *
     * @return list<self>
     */
    public static function views(): array
    {
        return [self::All, self::NoManager, self::ManyReports, self::InactiveManager];
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todas',
            self::NoManager => 'Sin jefe',
            self::ManyReports => 'Más de 15 directos',
            self::WithoutManager => 'Sin jefe',
            self::InactiveManager => 'Jefe dado de baja',
        };
    }

    /** Whether that many people are something to fix: any, except at the top, where one is expected; everybody is never. */
    public function pending(int $count): bool
    {
        return match ($this) {
            self::All => false,
            self::NoManager => $count > 1,
            default => $count > 0,
        };
    }
}
