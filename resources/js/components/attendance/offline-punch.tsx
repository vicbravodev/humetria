import { RotateCw } from 'lucide-react';
import { useState } from 'react';
import OfflineFrame from '@/components/attendance/offline-frame';
import PunchEveryStep from '@/components/attendance/punch-every-step';
import PunchOfflineNotes from '@/components/attendance/punch-offline-notes';
import PunchOfflineScreen from '@/components/attendance/punch-offline-screen';
import PunchScanFirst from '@/components/attendance/punch-scan-first';
import PersonHeading from '@/components/humetria/person-heading';
import { Button } from '@/components/ui/button';
import { useOfflineFlow } from '@/hooks/use-offline-flow';
import type { MarkType } from '@/lib/attendance/next-step';
import { needsScanFirst } from '@/lib/attendance/punch-view';

/**
 * Marcar in the shell of the installed app, what the phone opens with no network (the icon opens it): the four steps (the
 * server's `next` is not known here, so all are offered; a mark the policy asks the code of the screen for opens the reader
 * first, as Marcar does with a connection), the marks that wait and the way to try again with internet. No person is named: nothing of anyone is in the shell's page.
 */
export default function OfflinePunch({
    company,
    queue,
}: {
    company: string;
    queue: {
        slug: string;
        punchUrl: string;
        deliverUrl: string;
        ticketsUrl: string;
        homeUrl: string;
    };
}) {
    const flow = useOfflineFlow(queue);
    const [scanning, setScanning] = useState<MarkType | null>(null);
    // The marks that ask for the code are the policy's, kept in the phone with the tickets: none known, none asked.
    const qrTypes = flow.tickets.context?.qrTypes ?? [];
    const start = (type: MarkType) =>
        needsScanFirst(qrTypes, type, false)
            ? setScanning(type)
            : flow.start(type, null);

    if (flow.view !== null) {
        return (
            <PunchOfflineScreen
                company={company}
                slug={queue.slug}
                flow={flow}
                prepareUrl={queue.homeUrl}
            />
        );
    }

    return (
        <OfflineFrame
            company={company}
            actions={<PunchEveryStep onMark={start} />}
        >
            <div className="flex w-full max-w-md flex-col gap-4">
                <PersonHeading title="Marcar sin internet">
                    No hay internet. Su marca se guarda en este teléfono y se
                    envía sola cuando vuelva la señal.
                </PersonHeading>
                <PunchOfflineNotes flow={flow} correction={null} />
                <Button
                    asChild
                    variant="outline"
                    className="h-12 w-full text-base"
                >
                    <a href={queue.punchUrl}>
                        <RotateCw aria-hidden />
                        Reintentar con internet
                    </a>
                </Button>
            </div>
            {scanning !== null && (
                <PunchScanFirst
                    type={scanning}
                    slug={queue.slug}
                    onRead={(code, type) => {
                        setScanning(null);
                        flow.start(type, code);
                    }}
                    onClose={() => setScanning(null)}
                />
            )}
        </OfflineFrame>
    );
}
