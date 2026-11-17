import { useState } from 'react';
import PortalConsent from '@/components/seating/portal-consent';
import PortalQuestions from '@/components/seating/portal-questions';
import PortalSent from '@/components/seating/portal-sent';
import type { PortalCondition } from '@/types';

type Screen = 'consent' | 'questions' | 'sent';

/**
 * The two health questions as one task (finding 13): first who sees the answers and the choice to answer; then the questions,
 * one at a time; then the thanks, from where the person may change them. A person who already answered lands on the thanks.
 */
export default function PortalAnswers({
    page,
}: {
    page: {
        slug: string;
        company: string;
        notice: string;
        footwear: PortalCondition;
        discomfort: PortalCondition;
        sentOn: string | null;
    };
}) {
    const [screen, setScreen] = useState<Screen>(
        page.sentOn === null ? 'consent' : 'sent',
    );

    if (screen === 'sent' && page.sentOn !== null) {
        return (
            <PortalSent
                slug={page.slug}
                company={page.company}
                sentOn={page.sentOn}
                onAgain={() => setScreen('questions')}
            />
        );
    }

    if (screen === 'questions') {
        return (
            <PortalQuestions
                key={page.sentOn}
                page={page}
                onSent={() => setScreen('sent')}
            />
        );
    }

    return (
        <PortalConsent
            slug={page.slug}
            company={page.company}
            notice={page.notice}
            onAccept={() => setScreen('questions')}
        />
    );
}
