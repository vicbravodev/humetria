import OfflineQueueItem from '@/components/attendance/offline-queue-item';
import type { KeptMark } from '@/hooks/use-offline-queue';

/**
 * The marks the server did not accept, which stay in the phone until the person says they understood or asks RH for a
 * correction: the one place they are told of them (the marks that still wait are only counted, in the band above).
 */
export default function OfflineQueueList({
    kept,
    correction,
    online,
}: {
    kept: KeptMark[];
    correction: { optionsUrl: string; url: string } | null;
    online: boolean;
}) {
    if (kept.length === 0) {
        return null;
    }

    return (
        <section aria-label="Marcas no aceptadas" className="w-full">
            <ul className="flex flex-col gap-3">
                {kept.map((mark) => (
                    <OfflineQueueItem
                        key={mark.seq}
                        mark={mark}
                        correction={correction}
                        online={online}
                    />
                ))}
            </ul>
        </section>
    );
}
