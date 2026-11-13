import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PortalHomeLink from '@/components/portal-home-link';
import PortalConfirmation from '@/components/seating/portal-confirmation';
import PortalDeclined from '@/components/seating/portal-declined';
import PortalIntro from '@/components/seating/portal-intro';
import PortalQuestions from '@/components/seating/portal-questions';
import PortalSeatReport from '@/components/seating/portal-seat-report';
import PortalSent from '@/components/seating/portal-sent';
import PortalShell from '@/components/seating/portal-shell';
import type { PortalAcknowledgment as Pending, PortalCondition } from '@/types';

type Screen = 'intro' | 'questions' | 'declined' | 'sent';

/**
 * Board LS-Portal: the person's own Ley Silla page, in usted. The confirmation of their assessment when one waits; the two
 * questions on footwear and discomfort, voluntary, one at a time and only after the notice; and a damaged seat to report.
 */
export default function SeatingPortal({
    token,
    company,
    name,
    notice,
    footwear,
    discomfort,
    sentOn,
    acknowledgment,
    acknowledgedOn,
}: {
    token: string;
    company: string;
    name: string;
    notice: string;
    footwear: PortalCondition;
    discomfort: PortalCondition;
    /** The date of the person's last answer, if they ever sent one. */
    sentOn: string | null;
    /** The assessment waiting for the person's confirmation, if any. */
    acknowledgment: Pending | null;
    /** When the person confirmed their current assessment, if they did. */
    acknowledgedOn: string | null;
}) {
    const [shown, setScreen] = useState<Screen>(
        sentOn === null ? 'intro' : 'sent',
    );

    return (
        <PortalShell company={company}>
            <Head title="Su trabajo de pie" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <PortalHomeLink token={token} />
                <PortalConfirmation
                    token={token}
                    acknowledgment={acknowledgment}
                    acknowledgedOn={acknowledgedOn}
                />
                {shown === 'intro' && (
                    <PortalIntro
                        name={name}
                        notice={notice}
                        onAccept={() => setScreen('questions')}
                        onDecline={() => setScreen('declined')}
                    />
                )}
                {shown === 'questions' && (
                    <PortalQuestions
                        key={sentOn}
                        token={token}
                        footwear={footwear}
                        discomfort={discomfort}
                        onSent={() => setScreen('sent')}
                    />
                )}
                {shown === 'declined' && (
                    <PortalDeclined onAnswer={() => setScreen('intro')} />
                )}
                {shown === 'sent' && sentOn !== null && (
                    <PortalSent
                        sentOn={sentOn}
                        onAgain={() => setScreen('questions')}
                    />
                )}
                {shown !== 'questions' && <PortalSeatReport token={token} />}
            </div>
        </PortalShell>
    );
}
