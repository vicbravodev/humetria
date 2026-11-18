import ArcMeter from '@/components/humetria/arc-meter';

/**
 * When the code turns: the arch of the logo, emptying over the window (board «Checador (kiosco): QR de marca»), and what the
 * code is good for. The seconds left are its label, for whoever cannot see it.
 */
export default function KioskCountdown({
    left,
    window: seconds,
}: {
    left: number;
    window: number;
}) {
    return (
        <div className="flex items-center gap-5 card px-6 py-5">
            <ArcMeter
                value={left}
                max={seconds}
                label={`Cambia en ${left} s`}
                size="sm"
            />
            <p className="text-lg text-muted-foreground">
                <span className="font-semibold text-foreground">
                    Un código nuevo cada {seconds} segundos.
                </span>
                <br />
                Solo sirve en este centro.
            </p>
        </div>
    );
}
