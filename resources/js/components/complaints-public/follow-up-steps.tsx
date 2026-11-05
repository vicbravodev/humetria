import { shortDay } from '@/lib/complaints/deadline';
import { cn } from '@/lib/utils';
import type { FollowUp } from '@/types';

/** How the complaint has gone, in four days the person can read (board V4-Quejas-Seguimiento): never an hour. */
export default function FollowUpSteps({ view }: { view: FollowUp }) {
    const steps = [
        { label: 'Recibida', when: shortDay(view.receivedOn), done: true },
        {
            label: 'Alguien la leyó',
            when:
                view.acknowledgedOn === null
                    ? null
                    : shortDay(view.acknowledgedOn),
            done: view.acknowledgedOn !== null,
        },
        {
            label: 'En revisión',
            when: view.status === 'in_review' ? 'ahora' : null,
            done: view.status !== 'received',
        },
        { label: 'Respuesta', when: null, done: view.status === 'closed' },
    ];

    return (
        <ol aria-label="Cómo va" className="flex flex-col gap-3 card p-4">
            {steps.map((step) => (
                <li key={step.label} className="flex items-center gap-3">
                    <span
                        aria-hidden
                        className={cn(
                            'size-3 shrink-0 rounded-full',
                            step.done ? 'bg-primary' : 'border-2 border-track',
                        )}
                    />
                    <span
                        className={cn(
                            'flex-1',
                            !step.done && 'text-muted-foreground',
                        )}
                    >
                        {step.label}
                    </span>
                    {step.when !== null && (
                        <span className="text-sm text-muted-foreground">
                            {step.when}
                        </span>
                    )}
                    <span className="sr-only">
                        {step.done ? 'hecho' : 'pendiente'}
                    </span>
                </li>
            ))}
        </ol>
    );
}
