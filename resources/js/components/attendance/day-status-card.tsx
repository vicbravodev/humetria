import { Clock, Coffee, Flag, LogIn } from 'lucide-react';
import ToneTile from '@/components/attendance/tone-tile';
import type { Tone } from '@/components/attendance/tone-tile';
import type { DayStatus } from '@/lib/attendance/punch-view';

const LOOKS = {
    ready: { icon: LogIn, tone: 'accent' },
    working: { icon: Clock, tone: 'ok' },
    break: { icon: Coffee, tone: 'warning' },
    done: { icon: Flag, tone: 'ok' },
} as const satisfies Record<DayStatus['kind'], { icon: unknown; tone: Tone }>;

/** Where the person's day stands (board P2): an icon in the tone of the moment, the state in bold and one line under it. */
export default function DayStatusCard({ status }: { status: DayStatus }) {
    const { icon, tone } = LOOKS[status.kind];

    return (
        <section
            aria-label="Su jornada"
            className="flex items-center gap-4 card p-5"
        >
            <ToneTile icon={icon} tone={tone} />
            <div className="flex min-w-0 flex-col">
                <p className="text-xl leading-7 font-semibold">
                    {status.title}
                </p>
                <p className="text-base text-muted-foreground">{status.text}</p>
            </div>
        </section>
    );
}
