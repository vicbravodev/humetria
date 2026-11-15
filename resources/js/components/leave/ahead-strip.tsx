import { Link } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { stripDay } from '@/lib/leave/overview';
import { cn } from '@/lib/utils';
import { index as balances } from '@/routes/tenant/vacations/balances';
import type { LeaveAheadDay } from '@/types';

/**
 * «Los próximos 14 días»: how many people are out each day, a cell per day with the number and no names; a day with
 * someone out is tinted. It scrolls sideways on a phone. Under it, the windows about to close and the way to the balances.
 */
export default function AheadStrip({
    days,
    windowsClosing,
}: {
    days: LeaveAheadDay[];
    windowsClosing: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="leave-ahead"
            className="flex flex-col gap-4 card p-6"
        >
            <h2 id="leave-ahead" className="text-lg font-semibold">
                Los próximos 14 días
            </h2>
            <ol className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:grid lg:grid-cols-7 lg:overflow-visible">
                {days.map((day) => {
                    const { weekday, number } = stripDay(day.date);

                    return (
                        <li
                            key={day.date}
                            aria-label={`${weekday} ${number}: ${day.away} fuera`}
                            className={cn(
                                'flex min-w-11 flex-col items-center gap-0.5 rounded-lg border py-2 text-center',
                                day.away > 0 && 'bg-accent',
                            )}
                        >
                            <span className="text-caption text-muted-foreground">
                                {weekday}
                            </span>
                            <span className="text-sm tabular-nums">
                                {number}
                            </span>
                            <span
                                className={cn(
                                    'text-sm tabular-nums',
                                    day.away > 0
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {day.away}
                            </span>
                        </li>
                    );
                })}
            </ol>
            <p className="text-meta text-muted-foreground">
                Personas fuera por día, sin nombres.
            </p>
            <p className="border-t pt-3 text-meta">
                Ventanas por cerrar: <strong>{windowsClosing}</strong>{' '}
                <Link
                    href={balances.url(tenantSlug)}
                    className="touch-hit font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver saldos →
                </Link>
            </p>
        </section>
    );
}
