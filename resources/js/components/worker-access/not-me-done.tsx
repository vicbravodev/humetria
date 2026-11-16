import { CircleCheck } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import ToneTile from '@/components/humetria/tone-tile';

/** The key is no longer usable: nothing else to do but wait for RH's new link. */
export default function NotMeDone({ company }: { company: string }) {
    return (
        <WorkerFrame company={company}>
            <section role="status" className="flex flex-col items-start gap-3">
                <ToneTile icon={CircleCheck} tone="ok" large />
                <h1 className="text-2xl leading-8 font-semibold text-balance">
                    La llave quedó anulada
                </h1>
                <p className="text-muted-foreground">
                    Ya no sirve para entrar ni para marcar, y sus sesiones se
                    cerraron. Recursos Humanos recibió un aviso; pídales un
                    enlace nuevo para volver a entrar.
                </p>
            </section>
        </WorkerFrame>
    );
}
