import { Head } from '@inertiajs/react';
import PortalSeatReport from '@/components/seating/portal-seat-report';

/** The person reports a damaged seat, anonymously, on a screen of its own (findings 13 and 15). */
export default function SeatingPortalReport({
    slug,
    company,
}: {
    slug: string;
    company: string;
}) {
    return (
        <>
            <Head title="Avisar de un asiento dañado" />
            <PortalSeatReport slug={slug} company={company} />
        </>
    );
}
