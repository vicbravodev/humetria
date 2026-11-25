import PunchAgreement from '@/components/attendance/punch-agreement';
import PunchDone from '@/components/attendance/punch-done';
import PunchProblemView from '@/components/attendance/punch-problem-view';
import PunchProgress from '@/components/attendance/punch-progress';
import PunchScreenReady from '@/components/attendance/punch-screen-ready';
import PunchOfflineScreen from '@/components/attendance/punch-offline-screen';
import type { useOfflineAwarePunch } from '@/hooks/use-offline-aware-punch';
import type { PunchMode } from '@/lib/attendance/punch';
import { feedbackProblem } from '@/lib/attendance/punch-problem';
import { feedbackAgreement } from '@/lib/attendance/punch-state';

/**
 * The screen of Marcar that fits the moment: each test as it passes while a mark is checked, the mark taken, what went
 * wrong and what to do, the agreement to accept and mark with, or the screen before marking (where a step that does not
 * proceed is said).
 */
export default function PunchScreen({
    company,
    punch,
    page,
    now,
}: {
    company: string;
    punch: ReturnType<typeof useOfflineAwarePunch>;
    page: {
        slug: string;
        mode: PunchMode;
        scannedAt: string | null;
        qrTypes: string[] | null;
        weekUrl: string;
        enterUrl: string;
        correctionOptionsUrl: string;
        correctionUrl: string;
        timezone: string;
    };
    /** The server's time now. */
    now: number;
}) {
    const { feedback, phase, marks } = punch;
    const problem = feedbackProblem(feedback, punch.location);
    const agreement = feedbackAgreement(feedback);

    if (phase !== null) {
        return <PunchProgress company={company} phase={phase} />;
    }

    if (punch.offline.view !== null) {
        return (
            <PunchOfflineScreen
                company={company}
                slug={page.slug}
                flow={punch.offline}
                prepareUrl={page.enterUrl}
            />
        );
    }

    if (feedback?.kind === 'done' && marks !== null) {
        return (
            <PunchDone
                company={company}
                text={feedback.text}
                evidence={feedback.evidence ?? null}
                flag={feedback.flag ?? null}
                marks={marks}
                weekUrl={page.weekUrl}
                onDone={punch.dismiss}
            />
        );
    }

    if (agreement !== null) {
        return (
            <PunchAgreement
                company={company}
                agreement={agreement}
                step={punch.step}
                onAccept={() => punch.accept(agreement.version)}
                onLater={punch.dismiss}
            />
        );
    }

    if (problem !== null) {
        return (
            <PunchProblemView
                company={company}
                problem={problem}
                optionsUrl={page.correctionOptionsUrl}
                correctionUrl={page.correctionUrl}
                timezone={page.timezone}
                now={now}
                onRetry={punch.retry}
                onDismiss={punch.dismiss}
            />
        );
    }

    return (
        <PunchScreenReady
            company={company}
            punch={punch}
            page={page}
            now={now}
        />
    );
}
