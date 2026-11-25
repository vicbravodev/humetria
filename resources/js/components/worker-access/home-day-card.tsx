import { Link } from '@inertiajs/react';
import { Clock, Coffee, Fingerprint, Flag, LogIn } from 'lucide-react';
import ToneTile from '@/components/humetria/tone-tile';
import type { Tone } from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { nextSteps } from '@/lib/attendance/next-step';
import { dayStatus } from '@/lib/attendance/punch-view';
import type { DayStatus } from '@/lib/attendance/punch-view';
import { dayTimer } from '@/lib/worker-access/day-timer';
import type { WorkerDay } from '@/types';

const LOOKS = {
    ready: { icon: LogIn, tone: 'accent' },
    working: { icon: Clock, tone: 'ok' },
    break: { icon: Coffee, tone: 'warning' },
    done: { icon: Flag, tone: 'ok' },
} as const satisfies Record<DayStatus['kind'], { icon: unknown; tone: Tone }>;

/**
 * Where the person's day stands, on their start (board «Inicio»): the state, how long it has lasted, and the step that
 * follows. Marking asks for their key, so the steps take them to Marcar, which does; once the day ends, to their week.
 */
export default function HomeDayCard({
    day,
    punchUrl,
    weekUrl,
    now,
}: {
    day: WorkerDay;
    punchUrl: string;
    weekUrl: string;
    now: number;
}) {
    const status = dayStatus(day.next, day.marks);
    const { icon, tone } = LOOKS[status.kind];
    const timer = dayTimer(day.marks, now);
    const { primary, others } = nextSteps(day.next);
    const second = status.kind === 'working' ? others[0] : undefined;

    return (
        <section
            aria-label="Su jornada"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex items-center gap-3.5">
                <ToneTile icon={icon} tone={tone} />
                <div className="flex min-w-0 flex-col">
                    <p className="text-xl leading-7 font-semibold">
                        {status.title}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {status.text}
                    </p>
                </div>
            </div>
            {timer !== null && (
                <p className="flex items-baseline gap-2">
                    <span className="text-4xl leading-none font-semibold tracking-tight tabular-nums">
                        {timer.value}
                    </span>
                    <span className="text-base text-muted-foreground">
                        {timer.label}
                    </span>
                </p>
            )}
            {primary !== null && (
                <Button
                    asChild
                    className="h-14 w-full text-[17px] font-semibold"
                >
                    <Link href={punchUrl}>
                        <Fingerprint aria-hidden className="size-5" />
                        {primary.label}
                    </Link>
                </Button>
            )}
            {second !== undefined && (
                <Button
                    asChild
                    variant="outline"
                    className="h-12 w-full text-base"
                >
                    <Link href={punchUrl}>{second.label}</Link>
                </Button>
            )}
            {status.kind === 'done' && (
                <Button
                    asChild
                    variant="outline"
                    className="h-12 w-full text-base"
                >
                    <Link href={weekUrl}>Ver mi semana</Link>
                </Button>
            )}
        </section>
    );
}
