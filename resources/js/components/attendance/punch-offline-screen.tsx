import OfflineReadiness from '@/components/attendance/offline-readiness';
import PunchOfflineSaved from '@/components/attendance/punch-offline-saved';
import PunchPlanB from '@/components/attendance/punch-plan-b';
import type { OfflineFlow } from '@/hooks/use-offline-flow';

/**
 * The screen of Marcar that replaces it while a mark made without a connection is dealt with: «Guardada sin conexión», plan B
 * when the person's key did not sign, or what the phone lacks («Listo para marcar» / «Aún no está listo»). Nothing in it uses
 * Inertia: the shell of the installed app shows it too, with no page of the server behind. Its own header names the company.
 */
export default function PunchOfflineScreen({
    company,
    slug,
    flow,
    prepareUrl,
}: {
    company: string;
    slug: string;
    flow: OfflineFlow;
    /** Where this phone is prepared to sign (the person's account), when this page knows it. */
    prepareUrl: string | null;
}) {
    if (flow.view === 'saved') {
        return (
            <PunchOfflineSaved
                company={company}
                declared={flow.saved ?? ''}
                pending={flow.queue.pending}
                onDone={flow.dismiss}
            />
        );
    }

    if (flow.view === 'plan_b' || flow.view === 'plan_b_unavailable') {
        return (
            <PunchPlanB
                company={company}
                deviceKey={flow.view === 'plan_b'}
                busy={flow.busy}
                onMarkWithPhone={flow.markWithPhone}
                onRetry={flow.retryPasskey}
                onClose={flow.dismiss}
            />
        );
    }

    return flow.view === 'not_ready' ? (
        <OfflineReadiness
            company={company}
            slug={slug}
            readiness={flow.readiness}
            prepareUrl={prepareUrl}
            onClose={flow.dismiss}
        />
    ) : null;
}
