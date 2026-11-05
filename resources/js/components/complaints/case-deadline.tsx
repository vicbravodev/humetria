import DueBar from '@/components/complaints/due-bar';
import { dueText, elapsed, isUrgent } from '@/lib/complaints/deadline';
import { cn } from '@/lib/utils';

/** One deadline: met, or how far it is, with its bar. */
export default function CaseDeadline({
    label,
    due,
    met,
    received,
    today,
}: {
    label: string;
    due: string;
    met: boolean;
    received: string;
    today: string;
}) {
    const urgent = !met && isUrgent(today, due);
    const text = met ? 'Cumplido' : dueText('', today, due).trim();

    return (
        <div className="flex flex-col gap-2">
            <p className="flex flex-wrap justify-between gap-x-3 text-sm">
                <span className="font-medium">{label}</span>
                <span
                    className={cn(
                        urgent
                            ? 'font-medium text-risk-alto-fg'
                            : 'text-muted-foreground',
                    )}
                >
                    {text.charAt(0).toUpperCase() + text.slice(1)}
                </span>
            </p>
            <DueBar
                share={met ? 1 : elapsed(received, due, today)}
                urgent={urgent}
            />
        </div>
    );
}
