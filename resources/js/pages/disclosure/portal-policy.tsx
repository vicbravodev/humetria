import { Head } from '@inertiajs/react';
import PolicyAccordion from '@/components/disclosure/policy-accordion';
import PortalConfirm from '@/components/disclosure/portal-confirm';
import PortalConfirmed from '@/components/disclosure/portal-confirmed';
import PortalPolicyIntro from '@/components/disclosure/portal-policy-intro';
import WorkerFrame from '@/components/worker-access/worker-frame';
import { home } from '@/routes/worker';
import type { PortalPolicyProps } from '@/types';

/**
 * The policy of the person's center: the context in a line, the whole text folded by section and, while pending, the
 * confirmation stuck to the foot (board Politica/PoliticaNueva).
 */
export default function PortalPolicy({
    slug,
    company,
    policy,
    acknowledgment,
}: PortalPolicyProps) {
    const pending = acknowledgment?.state === 'pending';

    return (
        <WorkerFrame
            company={company}
            back={home.url(slug)}
            actions={
                policy !== null && pending ? (
                    <PortalConfirm
                        slug={slug}
                        documentId={acknowledgment.documentId}
                    />
                ) : undefined
            }
        >
            <Head title="La política de prevención" />
            <PortalPolicyIntro
                company={company}
                version={policy?.version ?? null}
                publishedOn={policy?.publishedOn}
                pending={pending}
            />
            {policy !== null && (
                <>
                    {acknowledgment?.state === 'confirmed' && (
                        <PortalConfirmed on={acknowledgment.on} />
                    )}
                    <PolicyAccordion sections={policy.sections} />
                </>
            )}
        </WorkerFrame>
    );
}
