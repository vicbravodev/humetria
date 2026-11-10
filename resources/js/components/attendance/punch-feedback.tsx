import { CircleAlert, CircleCheck } from 'lucide-react';
import type { PunchFeedback as Feedback } from '@/lib/attendance/punch-state';

/** How the last action went, in a band of its own (boards P2 to P4): done, or why not and what to do. */
export default function PunchFeedback({
    feedback,
}: {
    feedback: Feedback | null;
}) {
    if (feedback === null) {
        return null;
    }

    const done = feedback.kind === 'done';
    const Icon = done ? CircleCheck : CircleAlert;

    return (
        <p
            role={done ? 'status' : 'alert'}
            className={`flex items-start gap-3 rounded-xl px-4 py-3.5 text-base ${done ? 'bg-accent' : 'bg-risk-alto-bg text-risk-alto-fg'}`}
        >
            <Icon
                aria-hidden
                className={`mt-0.5 size-5 shrink-0 ${done ? 'text-primary' : ''}`}
            />
            {feedback.text}
        </p>
    );
}
