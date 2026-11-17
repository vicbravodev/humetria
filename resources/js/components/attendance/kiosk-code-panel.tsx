import KioskCountdown from '@/components/attendance/kiosk-countdown';
import type { KioskCode } from '@/lib/attendance/kiosk';

const STEPS = [
    'Apunte la cámara de su teléfono al código.',
    'Abra el enlace que aparece.',
    'Confirme con su huella, su rostro o su PIN.',
];

/**
 * The code to scan, large, with the three steps to mark and when it turns; before the first one, it says it is preparing it.
 * The code brings its own white ground and quiet zone, whatever the theme; it is as large as the screen's height allows, from
 * a 1366 × 768 laptop to a projector, without scrolling.
 */
export default function KioskCodePanel({
    code,
    left,
    qrSeconds,
}: {
    code: KioskCode | null;
    left: number;
    qrSeconds: number;
}) {
    return (
        <div className="grid w-full flex-1 items-center gap-10 lg:grid-cols-[minmax(0,1fr)_auto]">
            <div className="flex max-w-xl flex-col gap-7">
                <h1 className="text-4xl font-semibold text-balance sm:text-5xl">
                    Escanee para marcar
                </h1>
                <ol className="flex flex-col gap-4">
                    {STEPS.map((step, index) => (
                        <li
                            key={step}
                            className="flex items-center gap-4 text-xl sm:text-2xl"
                        >
                            <span
                                aria-hidden
                                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-lg font-semibold text-primary"
                            >
                                {index + 1}
                            </span>
                            {step}
                        </li>
                    ))}
                </ol>
                {code !== null && (
                    <KioskCountdown left={left} window={qrSeconds} />
                )}
            </div>
            <div
                data-kiosk-qr
                className="mx-auto aspect-square w-[min(100%,40rem,calc(100dvh-15rem))] overflow-hidden rounded-3xl shadow-raised"
            >
                {code === null ? (
                    <p className="flex size-full items-center justify-center card text-lg text-muted-foreground">
                        Preparando el código…
                    </p>
                ) : (
                    <img
                        src={code.qr}
                        alt="Código para registrar su entrada o su salida"
                        className="block size-full"
                    />
                )}
            </div>
        </div>
    );
}
