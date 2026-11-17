import { router } from '@inertiajs/react';
import { Fingerprint, ShieldCheck } from 'lucide-react';
import ProofSteps from '@/components/worker-access/proof-steps';
import StateMessage from '@/components/worker-access/state-message';
import StatusLine from '@/components/worker-access/status-line';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRecentProof } from '@/hooks/use-recent-proof';
import { proofKeyMessage } from '@/lib/worker-access/proof';
import type { ProofStep } from '@/types';

const TEXT =
    'Lo que va a abrir es delicado. Antes de mostrarlo, confirme que es usted; vale por unos minutos.';

/**
 * Before a delicate page (CUJ 5, finding 30): the person confirms it is them, and then it opens. A task screen: «Atrás» on
 * top. With a key it is a screen of state whose one action, the fingerprint, sits at the foot; without one, the code form.
 */
export default function ProofScreen({
    company,
    step,
    homeUrl,
}: {
    company: string;
    step: ProofStep;
    homeUrl: string;
}) {
    const proof = useRecentProof(step, (redirect) => router.visit(redirect));

    if (step.mode !== 'key') {
        return (
            <WorkerFrame company={company} back={homeUrl}>
                <PersonHeading title="Confirme que es usted">
                    {TEXT}
                </PersonHeading>
                <section className="flex flex-col gap-4 card p-5">
                    <ProofSteps step={step} proof={proof} />
                </section>
            </WorkerFrame>
        );
    }

    return (
        <WorkerFrame
            company={company}
            back={homeUrl}
            centered
            actions={
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={proof.working}
                    onClick={() => void proof.sign()}
                >
                    {proof.working ? (
                        <Spinner className="size-6" />
                    ) : (
                        <Fingerprint aria-hidden className="size-6" />
                    )}
                    Confirmar con mi huella
                </Button>
            }
        >
            <StateMessage icon={ShieldCheck} title="Confirme que es usted">
                Lo que va a abrir es delicado. Antes de mostrarlo, confirme que
                es usted con su huella, su rostro o su PIN. Vale por unos
                minutos.
            </StateMessage>
            {proof.keyFailure !== null && (
                <StatusLine text={proofKeyMessage(proof.keyFailure)} problem />
            )}
        </WorkerFrame>
    );
}
