import { Head } from '@inertiajs/react';
import { useState } from 'react';
import KioskScreen from '@/components/attendance/kiosk-screen';
import { useKioskCode } from '@/hooks/use-kiosk-code';
import { useNow } from '@/hooks/use-now';
import { useWakeLock } from '@/hooks/use-wake-lock';
import { clockOffset } from '@/lib/attendance/punch-view';

/**
 * The QR screen of a work center: no account, its token in an httpOnly cookie; it stays on and turns its code by itself. Its
 * clock is the server's (from the page, then from each code), in the zone of the center: a tablet with the wrong time
 * still shows the time the marks are taken at.
 */
export default function Kiosk({
    company,
    center,
    qrUrl,
    qrSeconds,
    timezone,
    timezoneLabel,
    now: serverNow,
}: {
    company: string;
    center: string;
    qrUrl: string;
    qrSeconds: number;
    timezone: string;
    /** The zone named with its offset: «Hora del Noroeste, UTC−8 · Tijuana». */
    timezoneLabel: string;
    /** The server's time when the page was made, in milliseconds. */
    now: number;
}) {
    useWakeLock();
    const [pageOffset] = useState(() => clockOffset(serverNow, Date.now()));
    const { code, receivedAt, online, lastValidAt, offset, retry } =
        useKioskCode(qrUrl, pageOffset);
    const now = useNow(250);

    return (
        <>
            <Head title={`${center} · ${company}`} />
            <KioskScreen
                company={company}
                center={center}
                code={code}
                receivedAt={receivedAt}
                online={online}
                lastValidAt={lastValidAt === null ? null : lastValidAt + offset}
                qrSeconds={qrSeconds}
                timezone={timezone}
                timezoneLabel={timezoneLabel}
                now={now}
                clock={now + offset}
                onRetry={retry}
            />
        </>
    );
}
