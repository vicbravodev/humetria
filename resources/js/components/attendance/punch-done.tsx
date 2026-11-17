import { Link } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import DayMarks from '@/components/attendance/day-marks';
import PersonFrame from '@/components/attendance/person-frame';
import { Button } from '@/components/ui/button';
import { flagNotice } from '@/lib/attendance/punch';
import type { DayMark } from '@/lib/attendance/punch';
import { doneTitle, markClock, markHow } from '@/lib/attendance/punch-view';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * The mark was taken (board «Asistencia: marca registrada»): its name, its time large and its own date in one group; one
 * notice at most (what RH will see flagged); the day in a card with how the mark was taken; that it cannot be erased, with
 * the way to a correction. «Listo» is the one action; «Ver mi semana» a link. The full sentence for a screen reader.
 */
export default function PunchDone({
    company,
    text,
    evidence,
    flag,
    marks,
    weekUrl,
    onDone,
}: {
    company: string;
    text: string;
    evidence: string | null;
    flag: string | null;
    marks: DayMark[];
    weekUrl: string;
    onDone: () => void;
}) {
    const last = marks.at(-1);
    const notice = flagNotice(flag);

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-[17px] font-semibold"
                        onClick={onDone}
                    >
                        Listo
                    </Button>
                    <Link
                        href={weekUrl}
                        className="flex min-h-12 items-center justify-center rounded-md text-[17px] font-medium text-primary focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Ver mi semana
                    </Link>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col items-center gap-5 text-center">
                <p role="status" className="sr-only">
                    {text}
                </p>
                <div className="flex animate-in flex-col items-center gap-1.5 duration-200 ease-out fade-in slide-in-from-bottom-1">
                    <span
                        aria-hidden
                        className="flex size-14 animate-in items-center justify-center rounded-[14px] bg-accent text-primary duration-200 ease-out zoom-in-96"
                    >
                        <CircleCheck className="size-7" />
                    </span>
                    <h1 className="mt-2 text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        {doneTitle(last?.type ?? '')}
                    </h1>
                    <p className="text-5xl leading-[52px] font-semibold tracking-[-0.03em] tabular-nums">
                        {last === undefined ? '' : markClock(last)}
                    </p>
                    {last?.date !== undefined && (
                        <p className="text-muted-foreground">
                            {dayTitle(last.date)}
                        </p>
                    )}
                </div>
                {notice !== null && (
                    <p className="flex w-full items-start gap-2.5 rounded-xl bg-warning px-3.5 py-3 text-left text-base text-warning-foreground">
                        <TriangleAlert
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0"
                        />
                        {notice}
                    </p>
                )}
                <div className="w-full text-left">
                    <DayMarks marks={marks} how={markHow(evidence, flag)} />
                </div>
                <p className="text-meta text-muted-foreground">
                    Su registro no se puede borrar. Si hay un error,{' '}
                    <Link
                        href={weekUrl}
                        className="-mx-1 inline-block px-1 py-2.5 font-medium text-primary underline underline-offset-[3px]"
                    >
                        pida una corrección
                    </Link>
                    .
                </p>
            </div>
        </PersonFrame>
    );
}
