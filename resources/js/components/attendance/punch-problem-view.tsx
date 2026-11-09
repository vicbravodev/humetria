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
import PersonFrame from '@/components/attendance/person-frame';
import PunchCorrection from '@/components/attendance/punch-correction';
import PunchProblemActions from '@/components/attendance/punch-problem-actions';
import ToneTile from '@/components/attendance/tone-tile';
import { useProblemActions } from '@/hooks/use-problem-actions';
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
 * A mark that was not taken (board P3): what happened in a title, what to do in a line, and the way out in the buttons at
 * the foot (try again, ask RH for a correction, how to allow the location, ask RH for the link). Nothing blames the person.
 */
export default function PunchProblemView({
    company,
    problem,
    optionsUrl,
    correctionUrl,
    timezone,
    onRetry,
    onDismiss,
}: {
    company: string;
    problem: Problem;
    optionsUrl: string;
    correctionUrl: string;
    timezone: string;
    onRetry: () => void;
    onDismiss: () => void;
}) {
    const { asking, howto, copied, run, close } = useProblemActions({
        company,
        timezone,
        onRetry,
        onDismiss,
    });

    return (
        <PersonFrame
            company={company}
            actions={
                asking === null ? (
                    <PunchProblemActions
                        actions={problem.actions}
                        onAction={run}
                    />
                ) : undefined
            }
        >
            <div className="flex w-full max-w-md flex-col gap-6">
                <section
                    role="alert"
                    className="flex flex-col items-start gap-3 pt-2"
                >
                    <ToneTile
                        icon={ICONS[problem.kind]}
                        tone={problem.tone}
                        large
                    />
                    <h1 className="text-2xl leading-8 font-semibold text-balance">
                        {problem.title}
                    </h1>
                    <p className="text-muted-foreground">{problem.text}</p>
                </section>
                {howto !== null && (
                    <p className="card p-5 text-base">{howto}</p>
                )}
                {copied && (
                    <p
                        role="status"
                        className="rounded-xl bg-accent px-4 py-3 text-base"
                    >
                        Mensaje copiado. Péguelo en un mensaje a Recursos
                        Humanos.
                    </p>
                )}
                <PunchCorrection
                    optionsUrl={optionsUrl}
                    url={correctionUrl}
                    asking={asking}
                    onClose={close}
                />
            </div>
        </PersonFrame>
    );
}
