import { CircleCheck } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import StateMessage from '@/components/worker-access/state-message';

/** The key is no longer usable: nothing else to do but wait for RH's new link. */
export default function NotMeDone({ company }: { company: string }) {
    return (
        <WorkerFrame company={company} centered>
            <StateMessage
                icon={CircleCheck}
                tone="ok"
                title="La llave quedó anulada"
                role="status"
            >
                Ya no sirve para entrar ni para marcar, y sus sesiones se
                cerraron. Recursos Humanos recibió un aviso; pídales un enlace
                nuevo para volver a entrar.
            </StateMessage>
        </WorkerFrame>
    );
}
