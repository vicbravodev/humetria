import HomeDayCard from '@/components/worker-access/home-day-card';
import HomeDayMarks from '@/components/worker-access/home-day-marks';
import { greeting, firstName } from '@/lib/attendance/punch-view';
import { useServerNow } from '@/hooks/use-server-now';
import type { WorkerDay } from '@/types';

const DATE = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/**
 * The person's start by the moment of their day (board «Inicio»): the greeting and the date at their center, where the day
 * stands with the step that follows, and the marks made. The hours count with the server's clock.
 */
export default function HomeDay({
    name,
    day,
    punchUrl,
    weekUrl,
    serverNow,
}: {
    name: string;
    day: WorkerDay;
    punchUrl: string;
    weekUrl: string;
    /** The server's time when the page was made, in milliseconds. */
    serverNow: number;
}) {
    const now = useServerNow(serverNow, 30_000);

    return (
        <>
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl leading-8 font-semibold tracking-tight text-balance">
                    {greeting(new Date(now).getHours())}, {firstName(name)}
                </h1>
                <p className="text-muted-foreground">
                    {DATE.format(now)} · {day.center}
                </p>
            </div>
            <HomeDayCard
                day={day}
                punchUrl={punchUrl}
                weekUrl={weekUrl}
                now={now}
            />
            <HomeDayMarks marks={day.marks} />
        </>
    );
}
