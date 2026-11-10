import { Link } from '@inertiajs/react';
import { CalendarDays, CircleCheck } from 'lucide-react';
import DayMarks from '@/components/attendance/day-marks';
import EvidenceDots from '@/components/attendance/evidence-dots';
import PersonFrame from '@/components/attendance/person-frame';
import ToneTile from '@/components/attendance/tone-tile';
import { Button } from '@/components/ui/button';
import { clockNow } from '@/lib/attendance/correction';
import { flagNotice } from '@/lib/attendance/punch';
import { doneTitle, evidenceText } from '@/lib/attendance/punch-view';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * The mark was taken (board P2, «Registrada»): its name, its time large, what it proves in words, that it cannot be erased
 * and how to ask for a correction; the full sentence for a screen reader, and the day below. «Listo» brings the next step.
 */
export default function PunchDone({
    company,
    text,
    evidence,
    flag,
    marks,
    timezone,
    now,
    weekUrl,
    onDone,
}: {
    company: string;
    text: string;
    evidence: string | null;
    flag: string | null;
    marks: { type: string; time: string }[];
    timezone: string;
    /** The time of this device's clock: the date and the greeting are the company's at that moment. */
    now: number;
    weekUrl: string;
    onDone: () => void;
}) {
    const last = marks.at(-1);
    const proof = evidenceText(evidence);
    const notice = flagNotice(flag);

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        onClick={onDone}
                    >
                        Listo
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="lg"
                        className="w-full text-base"
                    >
                        <Link href={weekUrl}>
                            <CalendarDays aria-hidden className="size-5" />
                            Ver mi semana
                        </Link>
                    </Button>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-6">
                <section className="flex flex-col items-start gap-3">
                    <p role="status" className="sr-only">
                        {text}
                    </p>
                    <ToneTile icon={CircleCheck} tone="ok" large />
                    <h1 className="text-2xl leading-8 font-semibold">
                        {doneTitle(last?.type ?? '')}
                    </h1>
                    <p className="text-5xl font-semibold tabular-nums">
                        {last?.time}
                    </p>
                    <p className="text-muted-foreground">
                        {dayTitle(clockNow(new Date(now), timezone).date)}
                    </p>
                    {proof !== null && (
                        <p className="flex items-center gap-2 text-base font-medium">
                            <EvidenceDots dots={proof.dots} />
                            {proof.text}
                        </p>
                    )}
                    {notice !== null && (
                        <p className="rounded-xl bg-warning px-4 py-3 text-base text-warning-foreground">
                            {notice}
                        </p>
                    )}
                </section>
                <p className="card p-5 text-base text-muted-foreground">
                    Su registro no se puede borrar. Si hay un error, puede pedir
                    una corrección.
                </p>
                <DayMarks marks={marks} />
            </div>
        </PersonFrame>
    );
}
