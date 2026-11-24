import { RotateCw } from 'lucide-react';
import { useState } from 'react';
import OfflineFrame from '@/components/attendance/offline-frame';
import PunchEveryStep from '@/components/attendance/punch-every-step';
import PunchOfflineNotes from '@/components/attendance/punch-offline-notes';
import PunchOfflineScreen from '@/components/attendance/punch-offline-screen';
import PunchScanButton from '@/components/attendance/punch-scan-button';
import PersonHeading from '@/components/humetria/person-heading';
import { Button } from '@/components/ui/button';
import { useOfflineFlow } from '@/hooks/use-offline-flow';

/**
 * Marcar in the shell of the installed app, what the phone opens with no network (the icon opens it): the four steps (the
 * server's `next` is not known here, so all are offered), the code of the screen of the center when the camera can read it, the
 * marks that wait and the way to try again with internet. No person is named: nothing of anyone is in the shell's page.
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
    const [code, setCode] = useState<string | null>(null);

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
            actions={
                <PunchEveryStep onMark={(type) => flow.start(type, code)} />
            }
        >
            <div className="flex w-full max-w-md flex-col gap-4">
                <PersonHeading title="Marcar sin internet">
                    No hay internet. Su marca se guarda en este teléfono y se
                    envía sola cuando vuelva la señal.
                </PersonHeading>
                <PunchOfflineNotes flow={flow} correction={null} />
                <PunchScanButton read={code !== null} onCode={setCode} />
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
        </OfflineFrame>
    );
}
