import KioskCountdown from '@/components/attendance/kiosk-countdown';
import type { KioskCode } from '@/lib/attendance/kiosk';

/** The code to scan, large, with how to scan it and when it turns; before the first one, it says it is preparing it. */
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
        <div className="grid w-full flex-1 items-center gap-10 lg:grid-cols-2">
            <div className="flex flex-col gap-6">
                <h1 className="text-4xl font-semibold text-balance sm:text-5xl">
                    Escanee para marcar
                </h1>
                <p className="text-xl text-muted-foreground sm:text-2xl">
                    Apunte la cámara de su teléfono al código y abra el enlace
                    para marcar su entrada o su salida.
                </p>
                {code !== null && (
                    <KioskCountdown left={left} window={qrSeconds} />
                )}
            </div>
            <div className="mx-auto aspect-square w-full max-w-xl card p-4 shadow-raised">
                {code === null ? (
                    <p className="flex size-full items-center justify-center text-lg text-muted-foreground">
                        Preparando el código…
                    </p>
                ) : (
                    <img
                        src={code.qr}
                        alt="Código para registrar su entrada o su salida"
                        className="block size-full rounded-md"
                    />
                )}
            </div>
        </div>
    );
}
