import { markName } from '@/lib/attendance/next-step';
import type { WorkerDay } from '@/types';

/** The marks of the person's day as chips (board «Inicio»): what they made, and a word on when the first one will be. */
export default function HomeDayMarks({ marks }: { marks: WorkerDay['marks'] }) {
    return (
        <section
            aria-labelledby="home-day-marks"
            className="flex flex-col gap-2"
        >
            <h2
                id="home-day-marks"
                className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
            >
                Hoy
            </h2>
            {marks.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    Todavía sin marcas. La primera será su entrada.
                </p>
            ) : (
                <ol className="flex flex-wrap gap-2">
                    {marks.map((mark) => (
                        <li
                            key={mark.at}
                            className="card px-3 py-2 text-base font-semibold"
                        >
                            {markName(mark.type)}{' '}
                            <span className="font-medium text-muted-foreground tabular-nums">
                                {mark.time}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
