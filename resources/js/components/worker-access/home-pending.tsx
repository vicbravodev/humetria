import { CircleCheck } from 'lucide-react';
import HomeSections from '@/components/worker-access/home-sections';
import StateMessage from '@/components/worker-access/state-message';
import PersonHeading from '@/components/humetria/person-heading';
import type { WorkerSection } from '@/types';

/** «Pendientes»: what waits for the person's answer or confirmation across their tools, or that they are up to date. */
export default function HomePending({ waiting }: { waiting: WorkerSection[] }) {
    if (waiting.length === 0) {
        return (
            <StateMessage icon={CircleCheck} tone="ok" title="Está al día">
                Nada espera su respuesta. Cuando llegue algo, aparecerá aquí.
            </StateMessage>
        );
    }

    return (
        <>
            <PersonHeading title="Pendientes">
                Lo que espera su respuesta o su confirmación.
            </PersonHeading>
            <HomeSections sections={waiting} label="Esperan su respuesta" />
        </>
    );
}
