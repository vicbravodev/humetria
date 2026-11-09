import { LocateFixed } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useZoneLocation } from '@/hooks/use-zone-location';
import type { Reading } from '@/lib/attendance/geolocation';
import { cn } from '@/lib/utils';

/** Fills a zone's center with where RH stands now, asked only on tap; says how precise it was, or why it could not. */
export default function ZoneLocationButton({
    onFound,
}: {
    onFound: (reading: Reading) => void;
}) {
    const { busy, text, failed, locate } = useZoneLocation(onFound);

    return (
        <div className="grid gap-2">
            <Button
                type="button"
                variant="outline"
                onClick={() => void locate()}
                disabled={busy}
                className="justify-self-start"
            >
                {busy ? <Spinner /> : <LocateFixed aria-hidden />}
                Usar mi ubicación actual
            </Button>
            <p
                role="status"
                className={cn(
                    'text-sm',
                    failed ? 'text-destructive' : 'text-muted-foreground',
                )}
            >
                {text ??
                    'Párate en el centro de la zona y tócalo: tu navegador pedirá permiso una vez.'}
            </p>
        </div>
    );
}
