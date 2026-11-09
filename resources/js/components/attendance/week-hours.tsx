import { TriangleAlert } from 'lucide-react';
import type { WeekView } from '@/lib/attendance/week';
import { duration, weekNotices } from '@/lib/attendance/week-text';

/** The week's hours as the law pays them, and what the person should notice. */
export default function WeekHours({ week }: { week: WeekView }) {
    const { summary } = week;
    const rows: { label: string; minutes: number }[] = [
        { label: 'Horas ordinarias', minutes: summary.ordinaryMinutes },
        { label: 'Horas extra dobles', minutes: summary.doubleMinutes },
        { label: 'Horas extra triples', minutes: summary.tripleMinutes },
        { label: 'En su día de descanso', minutes: summary.restDayMinutes },
    ];
    const notices = weekNotices(week);

    return (
        <section
            aria-labelledby="week-hours-title"
            className="flex flex-col gap-3 card p-5"
        >
            <h2
                id="week-hours-title"
                className="text-xl leading-7 font-semibold"
            >
                Sus horas
            </h2>
            <dl className="flex flex-col divide-y">
                {rows.map(({ label, minutes }) => (
                    <div
                        key={label}
                        className="flex items-center justify-between py-2 text-base"
                    >
                        <dt>{label}</dt>
                        <dd className="font-semibold tabular-nums">
                            {duration(minutes)}
                        </dd>
                    </div>
                ))}
            </dl>
            {notices.length > 0 && (
                <ul className="flex flex-col gap-2 rounded-lg bg-warning p-4 text-base text-warning-foreground">
                    {notices.map((notice) => (
                        <li key={notice} className="flex items-start gap-2">
                            <TriangleAlert
                                aria-hidden
                                className="mt-1 size-4 shrink-0"
                            />
                            {notice}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
