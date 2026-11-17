import { Pencil } from 'lucide-react';
import { markName } from '@/lib/attendance/next-step';
import { markClock, markHow } from '@/lib/attendance/punch-view';
import type { WeekMark as Mark } from '@/lib/attendance/week';

/**
 * One mark of a day on its line of time (board «Asistencia: su semana»): the time large on the left, its name and how it was
 * taken in words under it, why RH corrected it, and the pencil of 44 px that asks for its correction (none while one waits).
 */
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
    const how = [
        markHow(mark.corrected ? 'corrected' : mark.evidence),
        mark.nextDay ? 'del día siguiente' : null,
    ].filter((part) => part !== null);

    return (
        <li className="relative flex flex-col pl-[22px] before:absolute before:top-[26px] before:left-1 before:size-[9px] before:rounded-full before:bg-primary">
            <div className="flex min-h-14 items-center gap-3">
                <b className="min-w-[58px] text-[19px] leading-6 font-semibold tabular-nums">
                    {markClock(mark)}
                </b>
                <span className="flex flex-1 flex-col text-base leading-[22px]">
                    {markName(mark.type)}
                    {how.length > 0 && (
                        <small className="text-sm text-muted-foreground">
                            {how.join(' · ')}
                        </small>
                    )}
                </span>
                {!asked && (
                    <button
                        type="button"
                        aria-label={`Pedir corrección de ${markName(mark.type).toLowerCase()} de las ${mark.time}`}
                        className="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        onClick={onAsk}
                    >
                        <Pencil aria-hidden className="size-5" />
                    </button>
                )}
            </div>
            {mark.reason !== null && (
                <p className="pb-2 text-sm text-muted-foreground">
                    Motivo de la corrección: {mark.reason}
                </p>
            )}
        </li>
    );
}
