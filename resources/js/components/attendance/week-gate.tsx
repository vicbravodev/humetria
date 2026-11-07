import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Before anything of the person shows: their week opens only once their device verifies them. */
export default function WeekGate({
    working,
    onRead,
}: {
    working: boolean;
    onRead: () => void;
}) {
    return (
        <section
            aria-label="Ver mi semana"
            className="flex flex-col gap-4 card p-5"
        >
            <p className="text-base">
                Para ver sus marcas y sus horas, confirme que es usted con su
                huella, su rostro o su PIN. Nada de su semana se guarda en este
                teléfono.
            </p>
            <Button
                className="h-16 w-full text-lg font-semibold"
                disabled={working}
                onClick={onRead}
            >
                {working ? (
                    <Spinner className="size-6" />
                ) : (
                    <Fingerprint aria-hidden className="size-6" />
                )}
                Ver mi semana
            </Button>
        </section>
    );
}
