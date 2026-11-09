import { dayTimeline } from '@/lib/attendance/correction-effects';
import { markName } from '@/lib/attendance/next-step';
import type { CorrectionDetail } from '@/types';

/**
 * «Ese día en el registro» (board A3): the day's marks on an axis, the stretch worked shaded, and the mark asked as a dashed
 * line with a ring. It says the same to a screen reader as a sentence.
 */
export default function DayTimeline({
    day,
}: {
    day: NonNullable<CorrectionDetail['day']>;
}) {
    const { marks, asked } = day;
    const timeline = dayTimeline(day);
    const said = [
        marks.length === 0
            ? 'sin marcas'
            : marks
                  .map(
                      (mark) =>
                          `${markName(mark.type).toLowerCase()} ${mark.time}`,
                  )
                  .join(', '),
        `la marca pedida: ${markName(asked.type).toLowerCase()} ${asked.time}`,
    ].join('; ');

    return (
        <div className="flex flex-col gap-3">
            <div
                role="img"
                aria-label={`Ese día: ${said}`}
                className="relative h-16 overflow-hidden rounded-lg border bg-muted"
            >
                {timeline.band !== null && (
                    <div
                        className="absolute inset-y-0 bg-accent"
                        style={{
                            left: `${timeline.band.left}%`,
                            width: `${timeline.band.width}%`,
                        }}
                    />
                )}
                {timeline.marks.map((mark) => (
                    <span
                        key={`${mark.type}-${mark.label}`}
                        className="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-foreground"
                        style={{ left: `${mark.percent}%` }}
                    />
                ))}
                <div
                    className="absolute inset-y-2 w-0 border-l-[3px] border-dashed border-primary"
                    style={{ left: `${timeline.asked.percent}%` }}
                />
                <span
                    className="absolute top-2.5 size-5 -translate-x-1/2 rounded-full border-[3px] border-primary bg-card"
                    style={{ left: `${timeline.asked.percent}%` }}
                />
            </div>
            <div
                aria-hidden
                className="flex justify-between text-sm text-muted-foreground"
            >
                {timeline.ticks.map((tick) => (
                    <span key={tick}>{tick}</span>
                ))}
            </div>
            <p className="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                <span className="flex items-center gap-2">
                    <i
                        aria-hidden
                        className="h-0 w-3.5 border-t-[3px] border-dashed border-primary"
                    />
                    Marca que pide
                </span>
                <span className="flex items-center gap-2 text-muted-foreground">
                    <i
                        aria-hidden
                        className="size-2.5 rounded-full bg-foreground"
                    />
                    Marcas del día
                </span>
                <span className="flex items-center gap-2 text-muted-foreground">
                    <i
                        aria-hidden
                        className="size-2.5 rounded-[3px] bg-accent ring-1 ring-border"
                    />
                    Lo trabajado
                </span>
            </p>
        </div>
    );
}
