import { Head } from '@inertiajs/react';
import PortalTasks from '@/components/seating/portal-tasks';

/** The person's Ley Silla page: the list of their tasks, each on its own screen (finding 13). */
export default function SeatingPortal(page: {
    slug: string;
    company: string;
    homeUrl: string;
    /** Their current signed assessment waits for their confirmation. */
    pending: boolean;
    /** When the person confirmed their current assessment, if they did. */
    acknowledgedOn: string | null;
    /** The date of the person's last answer, if they ever sent one. */
    sentOn: string | null;
}) {
    return (
        <>
            <Head title="Su trabajo de pie" />
            <PortalTasks page={page} />
        </>
    );
}
