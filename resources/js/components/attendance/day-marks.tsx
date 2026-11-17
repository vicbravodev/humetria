import { markName } from '@/lib/attendance/next-step';
import type { DayMark } from '@/lib/attendance/punch';
import { markClock } from '@/lib/attendance/punch-view';

/**
 * The marks of the person's day, in order, at the time of where each was taken (with its zone when it was not their
 * center's); under the last one, how it was taken, in words (`how`), when the server said it.
 */
export default function DayMarks({
    marks,
    how = null,
}: {
    marks: DayMark[];
    how?: string | null;
}) {
    return (
        <section
            aria-labelledby="day-marks-title"
            className="flex flex-col card px-4 pt-3 pb-1"
        >
            <h2
                id="day-marks-title"
                className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
            >
                Su día
            </h2>
            {marks.length === 0 ? (
                <p className="py-3 text-base text-muted-foreground">
                    Aún no marca hoy.
                </p>
            ) : (
                <ol className="flex flex-col divide-y">
                    {marks.map((mark, index) => (
                        <li
                            key={mark.type + mark.time}
                            className="flex items-center justify-between gap-3 py-3 text-base"
                        >
                            <span className="flex flex-col">
                                {markName(mark.type)}
                                {how !== null && index === marks.length - 1 && (
                                    <small className="text-sm text-muted-foreground">
                                        {how}
                                    </small>
                                )}
                            </span>
                            <span className="text-right font-semibold tabular-nums">
                                {markClock(mark)}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
