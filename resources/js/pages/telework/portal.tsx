import { Head } from '@inertiajs/react';
import PortalShell from '@/components/telework/portal/portal-shell';
import PortalStart from '@/components/telework/portal/portal-start';
import PortalUnavailable from '@/components/telework/portal/portal-unavailable';
import type { PortalSection } from '@/types';

export default function Portal({
    unavailable,
    company,
    name,
    sections,
}: {
    unavailable: boolean;
    company: string | null;
    name: string | null;
    sections: PortalSection[];
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su portal" />
            {unavailable || name === null ? (
                <div className="w-full max-w-2xl">
                    <PortalUnavailable />
                </div>
            ) : (
                <PortalStart name={name} sections={sections} />
            )}
        </PortalShell>
    );
}
