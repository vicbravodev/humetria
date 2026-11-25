import Notice from '@/components/humetria/notice';
import {
    OPEN_ONLINE_SEND,
    alreadyRecordedText,
    queueCount,
    receivedText,
} from '@/lib/attendance/offline-text';
import type { QueueNotice } from '@/lib/pwa/queue';

/** What the last delivery said, once: the marks that were received, or that the server already had. */
function noticeLines(notices: QueueNotice[]): string[] {
    return [
        ...(notices.some((notice) => notice.kind === 'received')
            ? [receivedText()]
            : []),
        ...(notices.some((notice) => notice.kind === 'already_recorded')
            ? [alreadyRecordedText()]
            : []),
    ];
}

/**
 * The marks of the person that wait in the phone, as a band on top of Marcar and Mi semana: how many, and what comes next
 * (they go by themselves when there is a network). After a delivery it says once that they were received. It renders nothing
 * when there is nothing to say.
 */
export default function OfflineQueueCount({
    pending,
    notices,
    online,
}: {
    pending: number;
    notices: QueueNotice[];
    online: boolean;
}) {
    const lines = noticeLines(notices);

    if (pending === 0 && lines.length === 0) {
        return null;
    }

    return (
        <div className="flex w-full flex-col gap-2">
            {pending > 0 && (
                <Notice>
                    <strong>{queueCount(pending)}.</strong>{' '}
                    {online ? 'Enviando…' : OPEN_ONLINE_SEND}
                </Notice>
            )}
            {lines.map((line) => (
                <p
                    key={line}
                    role="status"
                    className="rounded-xl bg-accent px-4 py-3 text-base"
                >
                    {line}
                </p>
            ))}
        </div>
    );
}
