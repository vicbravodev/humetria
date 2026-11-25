import { Plus } from 'lucide-react';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * The days of the week the person has to cover before confirming it (criterio 5.2): a working day of their schedule, already
 * over, with no marks and no holiday, hire date or approved absence. Each one asks for the mark that was missing; if the person did
 * not work that day, Recursos Humanos records it as a leave and the day is covered. There is no form to justify an absence.
 */
export default function WeekUncoveredDays({
    dates,
    onAsk,
}: {
    dates: string[];
    /** Asks for a mark missing on this day. */
    onAsk: (date: string) => void;
}) {
    if (dates.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="week-uncovered-title"
            className="flex flex-col gap-3 card p-5"
        >
            <h2
                id="week-uncovered-title"
                className="text-xl leading-7 font-semibold"
            >
                Días sin cubrir
            </h2>
            <ul className="flex flex-col divide-y">
                {dates.map((date) => (
                    <li
                        key={date}
                        className="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <span className="text-base font-medium">
                            {dayTitle(date)}
                        </span>
                        <button
                            type="button"
                            className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-input bg-card px-3.5 text-base font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            onClick={() => onAsk(date)}
                        >
                            <Plus aria-hidden className="size-5 text-primary" />
                            Me faltó una marca
                        </button>
                    </li>
                ))}
            </ul>
            <p className="text-meta text-pretty text-muted-foreground">
                Si ese día no trabajó, pida a Recursos Humanos que lo registre
                como permiso. Cuando lo registren, el día quedará cubierto.
            </p>
        </section>
    );
}
