import { Check } from 'lucide-react';
import AssessmentSign from '@/components/seating/assessment-sign';
import { describeDate } from '@/lib/calendar-date';
import { cn } from '@/lib/utils';

/**
 * «Antes de firmar» (board LS-Evaluacion): what the signature needs, ticked as it is there, and the signature itself. What
 * the server still misses when signing shows under the button.
 */
export default function AssessmentChecklist({
    assessmentId,
    answered,
    conditions,
    verifiedOn,
    lacking,
    error,
}: {
    assessmentId: string;
    answered: number;
    conditions: number;
    verifiedOn: string | null;
    /** What else the signature asks for (the server's list), each shown as pending. */
    lacking: string[];
    error: string | undefined;
}) {
    const items = [
        {
            label: 'Las siete condicionantes',
            value: `${answered} de ${conditions}`,
            done: answered === conditions,
        },
        {
            label: 'Fecha de verificación',
            value:
                verifiedOn === null
                    ? 'Falta'
                    : (describeDate(verifiedOn) ?? verifiedOn),
            done: verifiedOn !== null,
        },
        ...lacking.map((text) => ({
            label: `${text.charAt(0).toUpperCase()}${text.slice(1)}`,
            value: 'Falta',
            done: false,
        })),
    ];

    return (
        <section
            aria-labelledby="before-signing"
            className="flex flex-col gap-4 card p-[22px]"
        >
            <h2 id="before-signing" className="text-sm font-semibold">
                Antes de firmar
            </h2>
            <ul className="flex flex-col gap-3">
                {items.map((item) => (
                    <li
                        key={item.label}
                        className="grid grid-cols-[24px_minmax(0,1fr)_auto] items-center gap-3 text-sm"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'inline-flex size-6 items-center justify-center rounded-full border-[1.5px] border-input',
                                item.done &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {item.done && <Check className="size-3.5" />}
                        </span>
                        <span>{item.label}</span>
                        <span className="text-muted-foreground">
                            {item.value}
                        </span>
                    </li>
                ))}
            </ul>
            <AssessmentSign assessmentId={assessmentId} error={error} />
            <p className="text-center text-sm text-muted-foreground">
                Después podrás canalizar, asentarla en un acta y proponer una
                medida.
            </p>
        </section>
    );
}
