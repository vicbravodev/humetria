import { Head } from '@inertiajs/react';
import ProofScreen from '@/components/worker-access/proof-screen';
import type { ProofStep } from '@/types';

/** Before a delicate page: the person confirms it is them with their key, or with a code by email. */
export default function Proof(props: {
    company: string;
    step: ProofStep;
    homeUrl: string;
}) {
    return (
        <>
            <Head title="Confirme que es usted" />
            <ProofScreen {...props} />
        </>
    );
}
