import { Head } from '@inertiajs/react';
import KioskScreen from '@/components/attendance/kiosk-screen';
import { useKioskCode } from '@/hooks/use-kiosk-code';
import { useWakeLock } from '@/hooks/use-wake-lock';

/** The QR screen of a work center: no account, its token in an httpOnly cookie; it stays on and turns its code by itself. */
export default function Kiosk({
    company,
    center,
    qrUrl,
}: {
    company: string;
    center: string;
    qrUrl: string;
}) {
    useWakeLock();
    const { code, online } = useKioskCode(qrUrl);

    return (
        <>
            <Head title={`${center} · ${company}`} />
            <KioskScreen
                company={company}
                center={center}
                code={code}
                online={online}
            />
        </>
    );
}
