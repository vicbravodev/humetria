import WeekDay from '@/components/attendance/week-day';
import type { WeekRequest } from '@/lib/attendance/correction';
import type { WeekDay as Day, WeekMark } from '@/lib/attendance/week';

/** The seven days of the week, folded, each with its marks and the way to ask for a correction (board «Asistencia: su semana»). */
export default function WeekDays({
    days,
    requests,
    onAsk,
}: {
    days: Day[];
    requests: WeekRequest[];
    /** Asks for the correction of a mark of a day, or of one missing (null). */
    onAsk: (date: string, mark: WeekMark | null) => void;
}) {
    const pending = requests.flatMap((request) =>
        request.status === 'pending' && request.markId !== null
            ? [request.markId]
            : [],
    );

    return (
        <section aria-labelledby="week-days-title">
            <h2
                id="week-days-title"
                className="mb-2 text-caption font-medium tracking-wide text-muted-foreground uppercase"
            >
                Sus marcas
            </h2>
            <ol className="flex flex-col card px-4">
                {days.map((day) => (
                    <WeekDay
                        key={day.date}
                        day={day}
                        pending={pending}
                        onAsk={(mark) => onAsk(day.date, mark)}
                    />
                ))}
            </ol>
            <p className="mt-3 text-meta text-muted-foreground">
                Si una marca está mal, toque el lápiz y Recursos Humanos la
                revisa.
            </p>
        </section>
    );
}
