import OfflineQueueCount from '@/components/attendance/offline-queue-count';
import { useOnline } from '@/hooks/use-online';
import type { QueueNotice } from '@/lib/pwa/queue';

/** The marks of the person that wait in the phone, on top of «Mi semana»: they are not in the week until they are sent. */
export default function WeekQueue({
    pending,
    notices,
}: {
    pending: number;
    notices: QueueNotice[];
}) {
    return (
        <OfflineQueueCount
            pending={pending}
            notices={notices}
            online={useOnline()}
        />
    );
}
