import { Head } from '@inertiajs/react';
import PunchScreen from '@/components/attendance/punch-screen';
import { useOfflineAwarePunch } from '@/hooks/use-offline-aware-punch';
import { useServerNow } from '@/hooks/use-server-now';
import type { PunchMode, Signer } from '@/lib/attendance/punch';

/**
 * A person marks their working day from their linked device (no account), screen by screen: before marking, each test as
 * it passes, the mark taken, or what went wrong and what to do. When nobody is known in this browser, every step is
 * offered. The clock is the server's, in the zone of the person's center. A mark refused can be asked of RH from here, and
 * the agreement, when it was not accepted, is accepted here with the mark.
 */
export default function Punch({
    company,
    slug,
    qr,
    person,
    mode,
    scannedAt,
    qrTypes,
    now,
    optionsUrl,
    punchUrl,
    forgetUrl,
    ticketsUrl,
    deliverUrl,
    weekUrl,
    enterUrl,
    correctionOptionsUrl,
    correctionUrl,
    timezone,
}: {
    company: string;
    /** The company's address segment: the phone keeps what it needs to mark without a connection under it. */
    slug: string;
    qr: string | null;
    person: Signer | null;
    mode: PunchMode;
    scannedAt: string | null;
    /** The marks that ask for the code; null when nobody is known (the reader is offered). */
    qrTypes: string[] | null;
    /** The server's time when the page was made, in milliseconds. */
    now: number;
    optionsUrl: string;
    punchUrl: string;
    forgetUrl: string;
    /** Where the phone swaps a ticket for a new batch, and where it delivers the marks it made without a connection. */
    ticketsUrl: string;
    deliverUrl: string;
    weekUrl: string;
    /** The person's portal: where a person without a key here adds one, and where «Atrás» goes. */
    enterUrl: string;
    correctionOptionsUrl: string;
    correctionUrl: string;
    timezone: string;
}) {
    const punch = useOfflineAwarePunch({
        slug,
        ticketsUrl,
        deliverUrl,
        optionsUrl,
        punchUrl,
        forgetUrl,
        qr,
        initial: person,
        mode,
    });
    const serverNow = useServerNow(now, 30_000);

    return (
        <>
            <Head title="Marcar asistencia" />
            <PunchScreen
                company={company}
                punch={punch}
                page={{
                    slug,
                    mode,
                    scannedAt,
                    qrTypes,
                    weekUrl,
                    enterUrl,
                    correctionOptionsUrl,
                    correctionUrl,
                    timezone,
                }}
                now={serverNow}
            />
        </>
    );
}
