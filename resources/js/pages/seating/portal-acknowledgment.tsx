import { Head } from '@inertiajs/react';
import PortalAcknowledged from '@/components/seating/portal-acknowledged';
import PortalAcknowledgment from '@/components/seating/portal-acknowledgment';
import type { PortalAcknowledgment as Pending } from '@/types';

/** The result of the person's assessment and its confirmation, on a screen of its own behind the proof (finding 34). */
export default function SeatingPortalAcknowledgment({
    slug,
    company,
    acknowledgment,
    acknowledgedOn,
}: {
    slug: string;
    company: string;
    /** The assessment waiting for the person's confirmation, if any. */
    acknowledgment: Pending | null;
    /** When the person confirmed their current assessment, if they did. */
    acknowledgedOn: string | null;
}) {
    return (
        <>
            <Head title="Resultado de su evaluación" />
            {acknowledgment !== null ? (
                <PortalAcknowledgment
                    slug={slug}
                    company={company}
                    pending={acknowledgment}
                />
            ) : (
                acknowledgedOn !== null && (
                    <PortalAcknowledged
                        slug={slug}
                        company={company}
                        acknowledgedOn={acknowledgedOn}
                    />
                )
            )}
        </>
    );
}
