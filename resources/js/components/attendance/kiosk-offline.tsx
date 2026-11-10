import { WifiOff } from 'lucide-react';
import ToneTile from '@/components/attendance/tone-tile';
import { Button } from '@/components/ui/button';
import { clockNow } from '@/lib/attendance/correction';

/**
 * The screen lost the server: no code at all (not the last one), why, when the last valid code was, and that it resumes
 * by itself. «Reintentar ahora» asks at once.
 */
export default function KioskOffline({
    lastValidAt,
    timezone,
    onRetry,
}: {
    lastValidAt: number | null;
    timezone: string;
    onRetry: () => void;
}) {
    return (
        <div className="flex max-w-2xl flex-col items-start gap-5">
            <ToneTile icon={WifiOff} tone="warning" large />
            <h1 className="text-4xl font-semibold text-balance">
                Esta pantalla perdió la conexión
            </h1>
            <p className="text-xl text-muted-foreground">
                No se muestran códigos mientras no haya internet, para que nadie
                marque con uno viejo. Se reanuda sola.
            </p>
            {lastValidAt !== null && (
                <p className="text-lg text-muted-foreground">
                    Último código válido:{' '}
                    <span className="font-semibold text-foreground tabular-nums">
                        {clockNow(new Date(lastValidAt), timezone).time}
                    </span>
                </p>
            )}
            <Button variant="outline" size="lg" onClick={onRetry}>
                Reintentar ahora
            </Button>
            <p className="max-w-xl text-base text-muted-foreground">
                Si sigue así, avise a RH: ellos pueden revisar la pantalla desde
                Centros de trabajo.
            </p>
        </div>
    );
}
