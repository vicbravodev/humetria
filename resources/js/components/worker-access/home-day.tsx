import HomeDayCard from '@/components/worker-access/home-day-card';
import HomeGreeting from '@/components/worker-access/home-greeting';
import HomeDayMarks from '@/components/worker-access/home-day-marks';
import { useServerNow } from '@/hooks/use-server-now';
import type { WorkerDay } from '@/types';

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
            <HomeGreeting
                name={name}
                where={day.center}
                serverNow={serverNow}
            />
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
