import KioskStatus from '@/components/attendance/kiosk-status';
import Logo from '@/components/humetria/logo';
import type { KioskCode } from '@/lib/attendance/kiosk';

/**
 * The QR screen of a work center, on a fixed tablet 1 or 2 m away: the center, a large code (dark on light in either
 * theme) and whether it still reaches the server. Speaks «usted» to the people who scan it.
 */
export default function KioskScreen({
    company,
    center,
    code,
    online,
}: {
    company: string;
    center: string;
    code: KioskCode | null;
    online: boolean;
}) {
    return (
        <main className="flex min-h-dvh flex-col items-center justify-between gap-6 bg-background p-6 text-foreground">
            <header className="flex w-full flex-wrap items-center justify-between gap-4">
                <Logo className="h-8" />
                <KioskStatus online={online} />
            </header>
            <section className="flex flex-col items-center gap-5 text-center">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    {company}
                </p>
                <h1 className="text-3xl font-semibold tracking-[-0.01em] sm:text-5xl">
                    {center}
                </h1>
                <div className="card p-3 shadow-raised">
                    {code === null ? (
                        <p className="flex size-[min(70vmin,36rem)] items-center justify-center text-lg text-muted-foreground">
                            Preparando el código…
                        </p>
                    ) : (
                        <img
                            src={code.qr}
                            alt="Código para registrar su entrada o su salida"
                            className="block size-[min(70vmin,36rem)] rounded-md"
                        />
                    )}
                </div>
                <p className="max-w-xl text-lg text-muted-foreground sm:text-2xl">
                    Escanee el código con la cámara de su teléfono para
                    registrar su entrada o su salida.
                </p>
            </section>
            <footer className="text-sm text-muted-foreground">
                El código cambia cada 30 segundos.
            </footer>
        </main>
    );
}
