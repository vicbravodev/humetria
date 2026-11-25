import OfflineQueueCount from '@/components/attendance/offline-queue-count';
import { Button } from '@/components/ui/button';
import { useOfflineQueue } from '@/hooks/use-offline-queue';
import { useOnline } from '@/hooks/use-online';

/**
 * What the installed app shows besides Marcar when the network is down: how many marks wait (and the delivery itself, which
 * starts as this opens and again when the network returns) and the way to Marcar, where they are made.
 */
export default function OfflineHomeQueue({
    slug,
    deliverUrl,
    punchUrl,
}: {
    slug: string;
    deliverUrl: string;
    punchUrl: string;
}) {
    const queue = useOfflineQueue({ slug, deliverUrl });
    const online = useOnline();

    return (
        <div className="flex w-full max-w-md flex-col gap-3">
            <OfflineQueueCount
                pending={queue.pending}
                notices={queue.notices}
                online={online}
            />
            <Button asChild size="lg" className="w-full">
                <a href={punchUrl}>Ir a Marcar</a>
            </Button>
        </div>
    );
}
