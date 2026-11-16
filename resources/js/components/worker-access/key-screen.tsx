import { Fingerprint } from 'lucide-react';
import KeySharedAccountNote from '@/components/worker-access/key-shared-account-note';
import StatusLine from '@/components/worker-access/status-line';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { KeyFailure } from '@/lib/worker-access/key';
import { keyProblem } from '@/lib/worker-access/key';

/** The device creates the person's key with their fingerprint, face or PIN: one touch, no password (CUJ 1). */
export default function KeyScreen({
    company,
    name,
    working,
    failure,
    onCreate,
}: {
    company: string;
    name: string;
    working: boolean;
    failure: KeyFailure | null;
    onCreate: () => void;
}) {
    const problem = failure === null ? null : keyProblem(failure);

    return (
        <WorkerFrame
            company={company}
            actions={
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={working}
                    onClick={onCreate}
                >
                    {working ? (
                        <Spinner className="size-6" />
                    ) : (
                        <Fingerprint aria-hidden className="size-6" />
                    )}
                    Crear llave
                </Button>
            }
        >
            <PersonHeading title={`${name}, cree su llave de acceso`}>
                Es la huella, el rostro o el PIN que ya usa para desbloquear
                este dispositivo. Con ella entrará sin esperar un correo.
            </PersonHeading>
            <section className="flex items-center gap-4 card p-5">
                <ToneTile icon={Fingerprint} />
                <p className="text-base text-muted-foreground">
                    El dispositivo le pedirá confirmar. Tarda unos segundos.
                </p>
            </section>
            <KeySharedAccountNote />
            {problem !== null && (
                <StatusLine
                    text={`${problem.title}. ${problem.text}`}
                    problem
                />
            )}
        </WorkerFrame>
    );
}
