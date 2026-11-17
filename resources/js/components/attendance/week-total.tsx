import { TriangleAlert } from 'lucide-react';
import WeekRibbon from '@/components/humetria/week-ribbon';
import type { WeekView } from '@/lib/attendance/week';
import { ribbonDays, weekNotices, weekTotal } from '@/lib/attendance/week-text';

/**
 * The week at a glance (board «Asistencia: su semana»): the total with its seven bars, ordinary and extra in a line each and
 * only the kinds of extra there are; under it, what the person should notice.
 */
export default function WeekTotal({ week }: { week: WeekView }) {
    const { total, lines, detail } = weekTotal(week);
    const notices = weekNotices(week);

    return (
        <>
            <section
                aria-labelledby="week-total-title"
                className="flex items-end justify-between gap-4 card p-4"
            >
                <div className="flex min-w-0 flex-col">
                    <h2
                        id="week-total-title"
                        className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Total de la semana
                    </h2>
                    <p className="text-[40px] leading-[44px] font-semibold tracking-[-0.02em] tabular-nums">
                        {total}
                    </p>
                    {lines.map((line) => (
                        <p
                            key={line}
                            className="text-base text-muted-foreground"
                        >
                            {line}
                        </p>
                    ))}
                    {detail !== null && (
                        <p className="text-sm text-muted-foreground">
                            {detail}
                        </p>
                    )}
                </div>
                <WeekRibbon days={ribbonDays(week.days)} size="md" />
            </section>
            {notices.length > 0 && (
                <ul className="flex flex-col gap-2 rounded-xl bg-warning px-3.5 py-3 text-base text-warning-foreground">
                    {notices.map((notice) => (
                        <li key={notice} className="flex items-start gap-2.5">
                            <TriangleAlert
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0"
                            />
                            {notice}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
