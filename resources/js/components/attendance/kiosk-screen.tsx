import KioskCodePanel from '@/components/attendance/kiosk-code-panel';
import KioskHeader from '@/components/attendance/kiosk-header';
import KioskOffline from '@/components/attendance/kiosk-offline';
import KioskStatus from '@/components/attendance/kiosk-status';
import Logo from '@/components/humetria/logo';
import type { KioskCode } from '@/lib/attendance/kiosk';
import { kioskView, secondsLeft } from '@/lib/attendance/kiosk';

/**
 * The QR screen of a work center, on a fixed tablet 1 or 2 m away (board P5): the center and the company's clock, a large
 * code with the seconds it has left, and whether it still reaches the server. Without connection it shows no code at all,
 * not the last one. Speaks «usted» to the people who scan it.
 */
export default function KioskScreen({
    company,
    center,
    code,
    receivedAt,
    online,
    lastValidAt,
    qrSeconds,
    timezone,
    now,
    onRetry,
}: {
    company: string;
    center: string;
    code: KioskCode | null;
    receivedAt: number;
    online: boolean;
    lastValidAt: number | null;
    qrSeconds: number;
    timezone: string;
    now: number;
    onRetry: () => void;
}) {
    const view = kioskView(code, online);
    const left =
        code === null
            ? 0
            : secondsLeft(code.expiresIn * 1000, now - receivedAt);

    return (
        <main className="flex min-h-dvh flex-col gap-8 bg-background p-6 text-foreground sm:p-10">
            <KioskHeader
                company={company}
                center={center}
                online={online}
                now={now}
                timezone={timezone}
            />
            <section className="flex flex-1 items-center">
                {view === 'offline' ? (
                    <KioskOffline
                        lastValidAt={lastValidAt}
                        timezone={timezone}
                        onRetry={onRetry}
                    />
                ) : (
                    <KioskCodePanel
                        code={view === 'code' ? code : null}
                        left={left}
                        qrSeconds={qrSeconds}
                    />
                )}
            </section>
            <footer className="flex items-center justify-between gap-4">
                {online ? <KioskStatus online /> : <span />}
                <Logo kind="seal" className="h-4" />
            </footer>
        </main>
    );
}
