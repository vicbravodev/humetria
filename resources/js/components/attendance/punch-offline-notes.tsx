import OfflineQueueCount from '@/components/attendance/offline-queue-count';
import OfflineQueueList from '@/components/attendance/offline-queue-list';
import PunchOfflineSheet from '@/components/attendance/punch-offline-sheet';
import { Button } from '@/components/ui/button';
import type { OfflineFlow } from '@/hooks/use-offline-flow';
import { useOnline } from '@/hooks/use-online';
import { FEW_TICKETS } from '@/lib/attendance/offline-text';

/**
 * What Marcar says about the marks made without a connection, above its content: how many wait and, once, that they were
 * received; the ones the server did not accept; the warning of few tickets and the way to ask whether this phone is ready.
 * It also holds the sheet that asks the hour of a mark without a QR. Nothing when the company has not switched the mode on and
 * there is nothing to say.
 */
export default function PunchOfflineNotes({
    flow,
    correction,
}: {
    flow: OfflineFlow;
    /** Where a correction is asked; null in the shell of the installed app. */
    correction: { optionsUrl: string; url: string } | null;
}) {
    const online = useOnline();
    const enabled = flow.tickets.context?.offline === true;

    return (
        <>
            <OfflineQueueCount
                pending={flow.queue.pending}
                notices={flow.queue.notices}
                online={online}
            />
            <OfflineQueueList
                kept={flow.queue.kept}
                correction={correction}
                online={online}
            />
            {enabled && flow.tickets.stock === 'low' && (
                <p
                    role="status"
                    className="rounded-xl bg-warning px-4 py-3 text-base text-warning-foreground"
                >
                    {FEW_TICKETS}
                </p>
            )}
            {enabled && (
                <Button
                    variant="outline"
                    className="h-12 w-full text-base"
                    onClick={flow.openReadiness}
                >
                    ¿Puedo marcar sin internet?
                </Button>
            )}
            <PunchOfflineSheet
                type={flow.asking}
                busy={flow.busy}
                onSave={flow.submit}
                onClose={flow.closeSheet}
            />
        </>
    );
}
