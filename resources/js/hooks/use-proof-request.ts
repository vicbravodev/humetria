import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PendingSend } from '@/lib/worker-access/proof';
import { pendingSend } from '@/lib/worker-access/proof';
import type { ProofStep } from '@/types';

/**
 * A delicate form of the person that the server did not take for want of a recent proof (the flash `proofRequired`): the
 * step to prove, and the form to send again once proved. Whatever form was sent last is the one sent again.
 */
export function useProofRequest() {
    const [step, setStep] = useState<ProofStep | null>(null);
    const last = useRef<PendingSend | null>(null);

    useEffect(() => {
        const offBefore = router.on('before', (event) => {
            last.current = pendingSend(event.detail.visit) ?? last.current;
        });
        const offFlash = router.on('flash', (event) => {
            const required = event.detail.flash.proofRequired;

            if (required !== undefined) {
                setStep(required);
            }
        });

        return () => {
            offBefore();
            offFlash();
        };
    }, []);

    const resend = () => {
        const send = last.current;
        setStep(null);

        if (send !== null) {
            router.visit(send.url, {
                method: send.method,
                data: send.data,
                preserveScroll: true,
            });
        }
    };

    return { step, resend, close: () => setStep(null) };
}
