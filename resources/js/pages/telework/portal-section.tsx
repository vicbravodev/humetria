import { Head } from '@inertiajs/react';
import PortalHome from '@/components/telework/portal/portal-home';
import PortalShell from '@/components/telework/portal/portal-shell';
import type { PortalPending } from '@/types';

/** The person's telework section, in their session. */
export default function PortalSection({
    slug,
    company,
    canNotify,
    pending,
}: {
    slug: string;
    company: string;
    canNotify: boolean;
    pending: PortalPending;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su teletrabajo" />
            <PortalHome slug={slug} canNotify={canNotify} pending={pending} />
        </PortalShell>
    );
}
