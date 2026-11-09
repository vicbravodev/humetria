import { Pencil } from 'lucide-react';
import { markName } from '@/lib/attendance/next-step';
import type { WeekMark as Mark } from '@/lib/attendance/week';

/** One mark of a day: its name and time, why RH corrected it; tapping it asks for its correction. */
export default function WeekMark({
    mark,
    asked,
    onAsk,
}: {
    mark: Mark;
    /** A request of this mark waits for RH: none more. */
    asked: boolean;
    onAsk: () => void;
}) {
    const line = (
        <>
            <span>
                {markName(mark.type)}
                {mark.corrected && (
                    <span className="text-muted-foreground">
                        {' '}
                        (corregida por RH)
                    </span>
                )}
            </span>
            <span className="flex items-center gap-2 font-semibold tabular-nums">
                {mark.time}
                {mark.nextDay && (
                    <span className="font-normal text-muted-foreground">
                        del día siguiente
                    </span>
                )}
                {!asked && (
                    <Pencil
                        aria-hidden
                        className="size-4 text-muted-foreground"
                    />
                )}
            </span>
        </>
    );

    return (
        <li className="flex flex-col text-base">
            {asked ? (
                <div className="flex min-h-11 flex-wrap items-center justify-between gap-2">
                    {line}
                </div>
            ) : (
                <button
                    type="button"
                    className="-mx-2 flex min-h-11 flex-wrap items-center justify-between gap-2 rounded-md px-2 text-left hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label={`Pedir corrección de ${markName(mark.type).toLowerCase()} de las ${mark.time}`}
                    onClick={onAsk}
                >
                    {line}
                </button>
            )}
            {mark.reason !== null && (
                <p className="pb-1 text-caption text-muted-foreground">
                    Motivo de la corrección: {mark.reason}
                </p>
            )}
        </li>
    );
}
