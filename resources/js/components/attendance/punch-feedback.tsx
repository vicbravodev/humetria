import { CircleAlert, CircleCheck } from 'lucide-react';
import type { PunchFeedback as Feedback } from '@/lib/attendance/punch-state';

/** How the last mark went: taken, or why not and what to do. */
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
            className={`flex items-start gap-3 card p-4 text-base ${done ? '' : 'text-destructive'}`}
        >
            <Icon
                aria-hidden
                className={`mt-0.5 size-5 shrink-0 ${done ? 'text-primary' : ''}`}
            />
            {feedback.text}
        </p>
    );
}
