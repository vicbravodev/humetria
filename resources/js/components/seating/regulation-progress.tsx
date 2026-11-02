import { Check } from 'lucide-react';
import type { RegulationRecord } from '@/types';

/** Where the regulation stands: drafted, signed, deposited, distributed, each with its date and the folio. */
export default function RegulationProgress({
    regulation,
}: {
    regulation: RegulationRecord;
}) {
    const steps = [
        { label: 'Borrador', done: true, detail: null },
        {
            label: 'Firmado',
            done: regulation.signedOn !== null,
            detail: regulation.signedOn,
        },
        {
            label: 'Depositado',
            done: regulation.depositedOn !== null,
            detail:
                regulation.depositedOn === null
                    ? null
                    : `${regulation.depositedOn} · folio ${regulation.depositFolio ?? ''}`,
        },
        {
            label: 'Repartido',
            done: regulation.distributedOn !== null,
            detail: regulation.distributedOn,
        },
    ];

    return (
        <ol
            aria-label="Avance del reglamento"
            className="grid gap-2 sm:grid-cols-4"
        >
            {steps.map((step) => (
                <li key={step.label} className="flex flex-col gap-0.5 card p-4">
                    <span className="flex items-center gap-1.5 font-medium">
                        {step.done && (
                            <Check
                                aria-hidden
                                className="size-4 text-primary"
                            />
                        )}
                        {step.label}
                    </span>
                    <span className="text-caption text-muted-foreground">
                        {step.done ? (step.detail ?? 'Listo') : 'Pendiente'}
                    </span>
                </li>
            ))}
        </ol>
    );
}
