import {
    Link2Off,
    MapPinOff,
    QrCode,
    ShieldAlert,
    Smartphone,
    Timer,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import ToneTile from '@/components/attendance/tone-tile';
import type { Problem, ProblemKind } from '@/lib/attendance/punch-problem';

const ICONS: Record<ProblemKind, LucideIcon> = {
    zone: MapPinOff,
    location: MapPinOff,
    qr: QrCode,
    device: Link2Off,
    verify: ShieldAlert,
    wait: Timer,
    browser: Smartphone,
    generic: TriangleAlert,
};

/**
 * What happened and what to do, for a person (board P3): an icon in the tone of the problem, the fact as the title and the
 * way out in a line. It speaks as an alert, so a screen reader says it when it appears. Nothing blames the person.
 */
export default function PersonProblem({ problem }: { problem: Problem }) {
    return (
        <section role="alert" className="flex flex-col items-start gap-3 pt-2">
            <ToneTile icon={ICONS[problem.kind]} tone={problem.tone} large />
            <h1 className="text-2xl leading-8 font-semibold text-balance">
                {problem.title}
            </h1>
            <p className="text-muted-foreground">{problem.text}</p>
        </section>
    );
}
