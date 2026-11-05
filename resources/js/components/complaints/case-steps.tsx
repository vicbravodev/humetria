import type { CaseStep } from '@/lib/complaints/deadline';
import { cn } from '@/lib/utils';

/** Recibida → Acuse enviado → En revisión → Cerrada (board V4-Quejas-Caso), with the day of each step when it is kept. */
export default function CaseSteps({ steps }: { steps: CaseStep[] }) {
    return (
        <ol
            aria-label="Etapas del caso"
            className="grid grid-cols-2 gap-x-2 gap-y-4 sm:grid-cols-4"
        >
            {steps.map((step) => (
                <li key={step.label} className="flex flex-col gap-2">
                    <span
                        aria-hidden
                        className={cn(
                            'h-1.5 rounded-full',
                            step.done ? 'bg-primary' : 'bg-track',
                        )}
                    />
                    <span className="text-sm">
                        <span className="font-medium">{step.label}</span>
                        {step.day !== null && (
                            <span className="text-muted-foreground">
                                {' '}
                                {step.day}
                            </span>
                        )}
                        <span className="sr-only">
                            {step.done ? ': hecho' : ': pendiente'}
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
