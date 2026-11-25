import { RotateCw, WifiOff } from 'lucide-react';
import OfflineFrame from '@/components/attendance/offline-frame';
import OfflineHomeQueue from '@/components/attendance/offline-home-queue';
import StatusMessage from '@/components/humetria/status-message';
import { Button } from '@/components/ui/button';

/**
 * The shell of the installed app (`/me/{slug}/offline`) for any page but Marcar: what the phone opens when there is no network
 * and the page asked for is not there. It carries no data of anyone: the company's name and the way back to the person's start
 * (it is saved in the phone's cache and shown to whoever holds the phone), and, with the registro de jornada, how many marks
 * wait in the phone and the way to Marcar, where they are made. Not an Inertia page: no frame, no props.
 */
export default function OfflineHome({
    company,
    homeUrl,
    queue,
}: {
    company: string;
    homeUrl: string;
    queue: { slug: string; deliverUrl: string; punchUrl: string } | null;
}) {
    return (
        <OfflineFrame company={company} centered>
            <div className="flex w-full flex-col items-center gap-6">
                <StatusMessage
                    icon={WifiOff}
                    title="No hay internet"
                    actions={
                        <Button asChild size="lg">
                            <a href={homeUrl}>
                                <RotateCw aria-hidden />
                                Reintentar
                            </a>
                        </Button>
                    }
                >
                    Esta pantalla necesita conexión. Cuando vuelva a tener
                    internet, ábrala de nuevo.
                </StatusMessage>
                {queue !== null && <OfflineHomeQueue {...queue} />}
            </div>
        </OfflineFrame>
    );
}
