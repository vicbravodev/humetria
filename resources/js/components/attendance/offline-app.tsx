import OfflineHome from '@/components/attendance/offline-home';
import OfflinePunch from '@/components/attendance/offline-punch';
import { usePathname } from '@/hooks/use-pathname';

/** What the shell knows about the queue of marks: the company's segment and where Marcar, the delivery and the tickets are. */
export type OfflineQueueUrls = {
    slug: string;
    punchUrl: string;
    deliverUrl: string;
    ticketsUrl: string;
};

/**
 * The root of the shell of the installed app (`/me/{slug}/offline`): what the phone opens when there is no network and the page
 * asked for is not there. At Marcar (the icon's address) it is Marcar without a connection; anywhere else it is the start with
 * the count of the marks that wait. It carries no data of anyone, and without the registro de jornada (no queue) it only says
 * there is no internet.
 */
export default function OfflineApp({
    company,
    homeUrl,
    queue,
}: {
    company: string;
    homeUrl: string;
    queue: OfflineQueueUrls | null;
}) {
    const pathname = usePathname();

    if (queue !== null && pathname === queue.punchUrl) {
        return <OfflinePunch company={company} queue={{ ...queue, homeUrl }} />;
    }

    return <OfflineHome company={company} homeUrl={homeUrl} queue={queue} />;
}
