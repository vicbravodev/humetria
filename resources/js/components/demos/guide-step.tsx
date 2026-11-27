import GuideActions from '@/components/demos/guide-actions';
import GuideLive from '@/components/demos/guide-live';
import GuidePortalPicker from '@/components/demos/guide-portal-picker';
import HumiAvatar from '@/components/humetria/humi-avatar';
import { Badge } from '@/components/ui/badge';
import { progressLabel } from '@/lib/demos/guide';
import type { DemoGuideStep } from '@/types/demo-guide';

// The step done on the presenter's own computer, in a person's portal (Demos\Support\Tours\LeaveTour).
const PORTAL_STEP = 'leave.key';

const LABEL =
    'text-xs font-semibold tracking-wide text-muted-foreground uppercase';

/** The current step: where it stands, «Qué hacer», «Qué está pasando», «Por qué importa» and what the presenter can do. */
export default function GuideStep({
    current,
    onSkip,
}: {
    current: DemoGuideStep;
    onSkip: (step: string) => void;
}) {
    return (
        <>
            <div className="flex items-center gap-3 pr-8">
                <HumiAvatar size={40} />
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <p className="text-meta text-muted-foreground tabular-nums">
                        {progressLabel(current)}
                    </p>
                    <h2 className="text-lg leading-6 font-semibold">
                        {current.title}
                    </h2>
                </div>
                {current.kind === 'do' ? (
                    <Badge>Hazlo</Badge>
                ) : (
                    <Badge variant="outline">Mira</Badge>
                )}
            </div>
            <div className="flex flex-col gap-1">
                <p className={LABEL}>Qué hacer</p>
                <p>{current.todo}</p>
            </div>
            {current.live !== null && (
                <div className="flex flex-col gap-1">
                    <p className={LABEL}>Qué está pasando</p>
                    <GuideLive
                        live={current.live}
                        count={current.count}
                        of={current.of}
                    />
                </div>
            )}
            <div className="flex flex-col gap-1 rounded-lg bg-muted p-3">
                <p className={LABEL}>Por qué importa</p>
                <p className="text-sm">{current.why}</p>
            </div>
            {current.key === PORTAL_STEP && <GuidePortalPicker />}
            {current.ask !== null && (
                <p className="text-meta text-muted-foreground">
                    Humi abre con la pregunta escrita: «{current.ask}»
                </p>
            )}
            <GuideActions current={current} onSkip={onSkip} />
        </>
    );
}
