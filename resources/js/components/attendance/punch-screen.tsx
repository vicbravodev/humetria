import PunchDone from '@/components/attendance/punch-done';
import PunchProblemView from '@/components/attendance/punch-problem-view';
import PunchProgress from '@/components/attendance/punch-progress';
import PunchReady from '@/components/attendance/punch-ready';
import type { usePunch } from '@/hooks/use-punch';
import { feedbackProblem } from '@/lib/attendance/punch-problem';

/**
 * The screen of Marcar that fits the moment: each test as it passes while a mark is checked, the mark taken, what went
 * wrong and what to do, or the screen before marking (where a mark that came out of order is said).
 */
export default function PunchScreen({
    company,
    punch,
    scanned,
    weekUrl,
    correctionOptionsUrl,
    correctionUrl,
    timezone,
    now,
}: {
    company: string;
    punch: ReturnType<typeof usePunch>;
    scanned: boolean;
    weekUrl: string;
    correctionOptionsUrl: string;
    correctionUrl: string;
    timezone: string;
    now: number;
}) {
    const { feedback, phase, marks } = punch;
    const problem = feedbackProblem(feedback, punch.location);

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
                timezone={timezone}
                now={now}
                weekUrl={weekUrl}
                onDone={punch.dismiss}
            />
        );
    }

    if (problem !== null) {
        return (
            <PunchProblemView
                company={company}
                problem={problem}
                optionsUrl={correctionOptionsUrl}
                correctionUrl={correctionUrl}
                timezone={timezone}
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
            scanned={scanned}
            weekUrl={weekUrl}
            timezone={timezone}
            now={now}
            onMark={(type) => void punch.mark(type)}
            onForget={punch.forget}
        />
    );
}
