import { Link, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import ProofSteps from '@/components/worker-access/proof-steps';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { useRecentProof } from '@/hooks/use-recent-proof';
import type { ProofStep } from '@/types';

/** Before a delicate page (CUJ 5): the person confirms it is them, and then it opens. */
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

    return (
        <WorkerFrame
            company={company}
            actions={
                <Button
                    asChild
                    variant="outline"
                    className="h-14 w-full text-lg"
                >
                    <Link href={homeUrl}>Volver a mi portal</Link>
                </Button>
            }
        >
            <PersonHeading title="Confirme que es usted">
                Lo que va a abrir es delicado. Antes de mostrarlo, confirme que
                es usted; vale por unos minutos.
            </PersonHeading>
            <section className="flex flex-col gap-4 card p-5">
                <ToneTile icon={ShieldCheck} />
                <ProofSteps step={step} proof={proof} />
            </section>
        </WorkerFrame>
    );
}
