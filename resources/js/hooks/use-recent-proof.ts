import { useState } from 'react';
import { deps, getKey } from '@/hooks/use-worker-entry';
import type { EntryFailure } from '@/lib/worker-access/entry';
import { enterWithKey } from '@/lib/worker-access/entry';
import type { CodeFailure } from '@/lib/worker-access/proof';
import { checkProofCode, sendProofCode } from '@/lib/worker-access/proof';
import type { ProofStep } from '@/types';

/**
 * The person proves it is them: their key signs (the same exchange as entering, against the step's addresses), or they ask
 * for a code by email and write it. `onProved` gets where the server says to go next.
 */
export function useRecentProof(
    step: ProofStep,
    onProved: (redirect: string) => void,
) {
    const [working, setWorking] = useState(false);
    const [keyFailure, setKeyFailure] = useState<EntryFailure | null>(null);
    const [codeFailure, setCodeFailure] = useState<CodeFailure | null>(null);
    const [sent, setSent] = useState(false);

    const sign = async () => {
        if (!('PublicKeyCredential' in window)) {
            setKeyFailure('unsupported');

            return;
        }

        setWorking(true);
        setKeyFailure(null);
        const outcome = await enterWithKey(
            { optionsUrl: step.optionsUrl, enterUrl: step.proveUrl },
            { ...deps(), getKey },
        );
        setWorking(false);

        if (typeof outcome === 'string') {
            setKeyFailure(outcome);
        } else {
            onProved(outcome.redirect);
        }
    };

    const askCode = async () => {
        setWorking(true);
        const outcome = await sendProofCode(step.sendCodeUrl, deps());
        setWorking(false);
        setSent(outcome === 'sent' || sent);
        setCodeFailure(outcome === 'sent' ? null : outcome);
    };

    const checkCode = async (code: string) => {
        setWorking(true);
        const outcome = await checkProofCode(step.checkCodeUrl, code, deps());
        setWorking(false);

        if (typeof outcome === 'string') {
            setCodeFailure(outcome);
        } else {
            onProved(outcome.redirect);
        }
    };

    return { working, keyFailure, codeFailure, sent, sign, askCode, checkCode };
}
