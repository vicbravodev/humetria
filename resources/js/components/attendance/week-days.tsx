import { Info } from 'lucide-react';
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
            id="week-days"
            aria-labelledby="week-days-title"
            className="flex scroll-mt-4 flex-col gap-1 card p-5"
        >
            <h2
                id="week-days-title"
                className="text-xl leading-7 font-semibold"
            >
                Sus marcas
            </h2>
            <p className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-base">
                <Info
                    aria-hidden
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
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
