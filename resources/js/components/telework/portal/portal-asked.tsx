import PortalPhotoRequestCard from '@/components/telework/portal/portal-photo-request-card';
import PortalVisitCard from '@/components/telework/portal/portal-visit-card';
import type { PortalPending } from '@/types';

/** What the Comisión asked of the person: photos of the workplace and the authorization of a visit. */
export default function PortalAsked({
    pending,
    token,
}: {
    pending: PortalPending;
    token: string;
}) {
    if (pending.photos.length === 0 && pending.visits.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="portal-asked" className="grid gap-3">
            <h2 id="portal-asked" className="text-xl font-semibold">
                La Comisión le pide
            </h2>
            <ul className="grid gap-3">
                {pending.photos.map((request) => (
                    <PortalPhotoRequestCard
                        key={request.id}
                        request={request}
                        token={token}
                    />
                ))}
                {pending.visits.map((visit) => (
                    <PortalVisitCard
                        key={visit.id}
                        visit={visit}
                        token={token}
                    />
                ))}
            </ul>
        </section>
    );
}
