import { Head } from '@inertiajs/react';
import PunchScreen from '@/components/attendance/punch-screen';
import { useNow } from '@/hooks/use-now';
import { usePunch } from '@/hooks/use-punch';
import type { Signer } from '@/lib/attendance/punch';

/**
 * A person marks their working day from their linked device (no account), screen by screen (board P2): before marking, each
 * test as it passes, the mark taken, or what went wrong and what to do (board P3). When nobody is known in this browser,
 * every step is offered. A mark refused can be asked of RH from here.
 */
export default function Punch({
    company,
    qr,
    person,
    optionsUrl,
    punchUrl,
    forgetUrl,
    weekUrl,
    correctionOptionsUrl,
    correctionUrl,
    timezone,
}: {
    company: string;
    qr: string | null;
    person: Signer | null;
    optionsUrl: string;
    punchUrl: string;
    forgetUrl: string;
    weekUrl: string;
    correctionOptionsUrl: string;
    correctionUrl: string;
    timezone: string;
}) {
    const punch = usePunch({
        optionsUrl,
        punchUrl,
        forgetUrl,
        qr,
        initial: person,
    });
    const now = useNow(30_000);

    return (
        <>
            <Head title="Marcar" />
            <PunchScreen
                company={company}
                punch={punch}
                scanned={qr !== null}
                weekUrl={weekUrl}
                correctionOptionsUrl={correctionOptionsUrl}
                correctionUrl={correctionUrl}
                timezone={timezone}
                now={now}
            />
        </>
    );
}
