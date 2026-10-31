import { Link2Off } from 'lucide-react';
import ArcMeter from '@/components/humetria/arc-meter';

/** A shared report whose link expired or was revoked (pantallas: Enlace-Vencido): what happened and what to do. */
export default function LinkUnavailable() {
    return (
        <main className="flex flex-1 items-center justify-center px-6 pb-24">
            <div className="flex max-w-[520px] flex-col items-center gap-5 text-center">
                <ArcMeter value={0} max={1} label="Enlace vencido" size="md">
                    <Link2Off
                        aria-hidden
                        className="mb-1 size-[30px] text-primary"
                    />
                </ArcMeter>
                <p className="text-sm font-medium text-muted-foreground">
                    Enlace vencido
                </p>
                <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em] text-balance">
                    Este enlace ya no está disponible
                </h1>
                <p className="text-[17px] leading-[26px] text-muted-foreground">
                    Venció o quien lo compartió lo desactivó. Pídele un enlace
                    nuevo a quien te lo envió.
                </p>
            </div>
        </main>
    );
}
