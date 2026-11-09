import { ribbonBars, ribbonLabel } from '@/lib/attendance/week-ribbon';
import type { RibbonDay } from '@/lib/attendance/week-ribbon';
import { cn } from '@/lib/utils';

/**
 * La cinta de la semana (board A1 of the Asistencia redesign): seven bars, Monday to Sunday, each the hours of a day with
 * its overtime on top; a bar's top is 12 h, and a day beyond it turns its overtime red. Pass `days` as `{ worked, overtime }`
 * in minutes (missing days are empty); `size` is `sm` for a table row and `md` for a card. It says its hours to a screen
 * reader as one image; the colors are explained by `<WeekRibbonLegend>`.
 */
export default function WeekRibbon({
    days,
    size = 'sm',
    className,
}: {
    days: readonly RibbonDay[];
    size?: 'sm' | 'md';
    className?: string;
}) {
    return (
        <div
            role="img"
            aria-label={ribbonLabel(days)}
            className={cn('flex items-end gap-1', className)}
        >
            {ribbonBars(days).map((bar, index) => (
                <div
                    key={index}
                    className={cn(
                        'flex flex-col justify-end overflow-hidden rounded-[4px] bg-track',
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
            ))}
        </div>
    );
}
