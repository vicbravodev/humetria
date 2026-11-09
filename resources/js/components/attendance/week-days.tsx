import WeekDay from '@/components/attendance/week-day';
import type { CorrectionDraft, WeekRequest } from '@/lib/attendance/correction';
import type { WeekDay as Day } from '@/lib/attendance/week';

/** The seven days of the week, each with its marks and the way to ask for a correction. */
export default function WeekDays({
    days,
    requests,
    working,
    onCorrect,
}: {
    days: Day[];
    requests: WeekRequest[];
    working: boolean;
    onCorrect: (draft: CorrectionDraft) => Promise<boolean>;
}) {
    const pending = requests.flatMap((request) =>
        request.status === 'pending' && request.markId !== null
            ? [request.markId]
            : [],
    );

    return (
        <section
            aria-labelledby="week-days-title"
            className="flex flex-col gap-1 card p-5"
        >
            <h2
                id="week-days-title"
                className="text-xl leading-7 font-semibold"
            >
                Sus marcas
            </h2>
            <p className="text-base text-muted-foreground">
                Si una marca está mal, tóquela para pedir a Recursos Humanos que
                la corrija.
            </p>
            <ol className="flex flex-col divide-y">
                {days.map((day) => (
                    <WeekDay
                        key={day.date}
                        day={day}
                        pending={pending}
                        working={working}
                        onCorrect={onCorrect}
                    />
                ))}
            </ol>
        </section>
    );
}
