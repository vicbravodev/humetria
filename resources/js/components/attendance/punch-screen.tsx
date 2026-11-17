import PunchAgreement from '@/components/attendance/punch-agreement';
import PunchDone from '@/components/attendance/punch-done';
import PunchProblemView from '@/components/attendance/punch-problem-view';
import PunchProgress from '@/components/attendance/punch-progress';
import PunchReady from '@/components/attendance/punch-ready';
import type { usePunch } from '@/hooks/use-punch';
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
    punch: ReturnType<typeof usePunch>;
    page: {
        mode: PunchMode;
        scannedAt: string | null;
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
        <PunchReady
            company={company}
            person={punch.person}
            marks={marks}
            feedback={feedback}
            mode={page.mode}
            scannedAt={page.scannedAt}
            weekUrl={page.weekUrl}
            enterUrl={page.enterUrl}
            timezone={page.timezone}
            now={now}
            onMark={(type) => void punch.mark(type)}
            onForget={punch.forget}
        />
    );
}
