import { markName } from '@/lib/attendance/next-step';

/** The marks of the person's day, in order, with the server's local time. */
export default function DayMarks({
    marks,
}: {
    marks: { type: string; time: string }[];
}) {
    return (
        <section
            aria-labelledby="day-marks-title"
            className="flex flex-col gap-3 card p-5"
        >
            <h2
                id="day-marks-title"
                className="text-xl leading-7 font-semibold"
            >
                Su día
            </h2>
            {marks.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    Aún no marca hoy.
                </p>
            ) : (
                <ol className="flex flex-col divide-y">
                    {marks.map((mark) => (
                        <li
                            key={mark.type + mark.time}
                            className="flex items-center justify-between py-2.5 text-base"
                        >
                            <span>{markName(mark.type)}</span>
                            <span className="font-semibold tabular-nums">
                                {mark.time}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
