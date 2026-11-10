import {
    RIBBON_INITIALS,
    ribbonAway,
    ribbonBars,
    ribbonLabel,
} from '@/lib/attendance/week-ribbon';
import type { RibbonDay } from '@/lib/attendance/week-ribbon';
import { cn } from '@/lib/utils';

/**
 * La cinta de la semana (board A1 of the Asistencia redesign): seven bars, Monday to Sunday, each the hours of a day with
 * its overtime on top; a bar's top is 12 h, and a day beyond it turns its overtime red. Pass `days` as `{ worked, overtime }`
 * in minutes (missing days are empty); a day with `absent: true` and no hours (vacations or a leave) is drawn with a dashed
 * outline, not as a day with no hours. `size` is `sm` for a table row and `md` for a card. It says its hours to a screen
 * reader as one image; the colors are explained by `<WeekRibbonLegend>`.
 */
export default function WeekRibbon({
    days,
    size = 'sm',
    labeled = false,
    className,
}: {
    days: readonly RibbonDay[];
    size?: 'sm' | 'md';
    /** The initial of each day under its bar (board P2 and P4, where the ribbon is read on its own). */
    labeled?: boolean;
    className?: string;
}) {
    const away = ribbonAway(days);

    return (
        <div
            role="img"
            aria-label={ribbonLabel(days)}
            className={cn('flex items-end gap-1', className)}
        >
            {ribbonBars(days).map((bar, index) => (
                <div key={index} className="flex flex-col items-center gap-1">
                    <div
                        className={cn(
                            'flex flex-col justify-end overflow-hidden rounded-[4px]',
                            away[index] === true
                                ? 'border border-dashed border-muted-foreground'
                                : 'bg-track',
                            size === 'sm' ? 'h-[34px] w-[11px]' : 'h-14 w-4',
                        )}
                    >
                        <span
                            className={cn(
                                'block w-full',
                                bar.over ? 'bg-risk-muy-alto' : 'bg-risk-medio',
                            )}
                            style={{ height: `${bar.overtime}%` }}
                        />
                        <span
                            className="block w-full bg-primary"
                            style={{ height: `${bar.ordinary}%` }}
                        />
                    </div>
                    {labeled && (
                        <span
                            aria-hidden
                            className="w-4 text-center text-caption text-muted-foreground"
                        >
                            {RIBBON_INITIALS[index]}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}
