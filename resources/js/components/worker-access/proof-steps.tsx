import { Fingerprint } from 'lucide-react';
import ProofCodeForm from '@/components/worker-access/proof-code-form';
import StatusLine from '@/components/worker-access/status-line';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { useRecentProof } from '@/hooks/use-recent-proof';
import { proofKeyMessage } from '@/lib/worker-access/proof';
import type { ProofStep } from '@/types';

/**
 * How this person proves it is them: their key (never a code then), a code by email without a key, or, with neither, the way
 * to Recursos Humanos.
 */
export default function ProofSteps({
    step,
    proof,
}: {
    step: ProofStep;
    proof: ReturnType<typeof useRecentProof>;
}) {
    if (step.mode === 'none') {
        return (
            <StatusLine
                text="No tiene una llave ni un correo registrado para confirmar que es usted. Pida su acceso a Recursos Humanos."
                problem
            />
        );
    }

    if (step.mode === 'code') {
        return <ProofCodeForm minutes={step.minutes} proof={proof} />;
    }

    return (
        <div className="flex flex-col gap-3">
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
            {proof.keyFailure !== null && (
                <StatusLine text={proofKeyMessage(proof.keyFailure)} problem />
            )}
        </div>
    );
}
