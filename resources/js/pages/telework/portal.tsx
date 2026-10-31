import { Head } from '@inertiajs/react';
import PortalHome from '@/components/telework/portal/portal-home';
import PortalShell from '@/components/telework/portal/portal-shell';
import PortalUnavailable from '@/components/telework/portal/portal-unavailable';
import type { PortalPending } from '@/types';

export default function Portal({
    unavailable,
    token,
    company,
    name,
    canNotify,
    pending,
}: {
    unavailable: boolean;
    token: string | null;
    company: string | null;
    name: string | null;
    canNotify: boolean;
    pending: PortalPending;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su teletrabajo" />
            {unavailable || name === null || token === null ? (
                <div className="w-full max-w-2xl">
                    <PortalUnavailable />
                </div>
            ) : (
                <PortalHome
                    name={name}
                    token={token}
                    canNotify={canNotify}
                    pending={pending}
                />
            )}
        </PortalShell>
    );
}
