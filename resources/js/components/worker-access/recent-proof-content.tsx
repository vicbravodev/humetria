import ProofSteps from '@/components/worker-access/proof-steps';
import {
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useRecentProof } from '@/hooks/use-recent-proof';
import type { ProofStep } from '@/types';

/** The step to prove inside the dialog a delicate form opens; once proved, the form goes again. */
export default function RecentProofContent({
    step,
    onProved,
}: {
    step: ProofStep;
    onProved: () => void;
}) {
    const proof = useRecentProof(step, onProved);

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Confirme que es usted</DialogTitle>
                <DialogDescription>
                    Es un paso delicado. Confirme que es usted y lo enviamos
                    enseguida.
                </DialogDescription>
            </DialogHeader>
            <ProofSteps step={step} proof={proof} />
        </DialogContent>
    );
}
