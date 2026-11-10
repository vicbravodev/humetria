import { Head } from '@inertiajs/react';
import KioskScreen from '@/components/attendance/kiosk-screen';
import { useKioskCode } from '@/hooks/use-kiosk-code';
import { useNow } from '@/hooks/use-now';
import { useWakeLock } from '@/hooks/use-wake-lock';

/** The QR screen of a work center: no account, its token in an httpOnly cookie; it stays on and turns its code by itself. */
export default function Kiosk({
    company,
    center,
    qrUrl,
    qrSeconds,
    timezone,
}: {
    company: string;
    center: string;
    qrUrl: string;
    qrSeconds: number;
    timezone: string;
}) {
    useWakeLock();
    const { code, receivedAt, online, lastValidAt, retry } =
        useKioskCode(qrUrl);
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
                lastValidAt={lastValidAt}
                qrSeconds={qrSeconds}
                timezone={timezone}
                now={now}
                onRetry={retry}
            />
        </>
    );
}
