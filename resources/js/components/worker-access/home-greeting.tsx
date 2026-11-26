import { useServerNow } from '@/hooks/use-server-now';
import { firstName, greeting } from '@/lib/attendance/punch-view';

const DATE = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/**
 * The greeting of the person's start, by the hour they open it («Buenas tardes, Ana»), and the date; with where they work
 * when the company keeps their day, and the company's name when it does not. The hour is the server's.
 */
export default function HomeGreeting({
    name,
    where,
    serverNow,
}: {
    name: string;
    /** The center the date is said at, or the company's name. */
    where: string;
    /** The server's time when the page was made, in milliseconds. */
    serverNow: number;
}) {
    const now = useServerNow(serverNow, 30_000);

    return (
        <div className="flex flex-col gap-1">
            <h1 className="text-2xl leading-8 font-semibold tracking-tight text-balance">
                {greeting(new Date(now).getHours())}, {firstName(name)}
            </h1>
            <p className="text-muted-foreground">
                {DATE.format(now)} · {where}
            </p>
        </div>
    );
}
