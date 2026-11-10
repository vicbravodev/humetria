import FlowProgress from '@/components/humetria/flow-progress';
import { stepProgress } from '@/lib/attendance/enroll';
import type { EnrollStep } from '@/lib/attendance/enroll';

/** «Paso 2 de 4» and its bar, on top of every step of linking the phone; nothing once the person left the way. */
export default function EnrollProgress({ step }: { step: EnrollStep }) {
    const progress = stepProgress(step);

    return progress === null ? null : (
        <FlowProgress
            label={progress.label}
            left={null}
            value={progress.value}
            max={progress.max}
        />
    );
}
