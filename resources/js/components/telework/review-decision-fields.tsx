import { useState } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DECISIONS, decisionNeeds } from '@/lib/telework/review';
import type { ReviewDecisionValue } from '@/types';

const CARD =
    'flex cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

/** The decision of the Comisión: the three outcomes, the notes, and the plan with its date when it validates with one. */
export default function ReviewDecisionFields({
    idPrefix,
    errors,
}: {
    idPrefix: string;
    errors: Partial<Record<string, string>>;
}) {
    const [decision, setDecision] = useState<ReviewDecisionValue>('valid');
    const needs = decisionNeeds(decision);

    return (
        <>
            <fieldset className="grid gap-2">
                <legend className="mb-1 font-medium">Decisión</legend>
                {DECISIONS.map((option) => (
                    <label key={option.value} className={CARD}>
                        <input
                            type="radio"
                            name="decision"
                            value={option.value}
                            checked={decision === option.value}
                            onChange={() => setDecision(option.value)}
                            className="mt-1 size-5 shrink-0 accent-primary"
                        />
                        <span className="grid">
                            <span className="font-medium">{option.label}</span>
                            <span className="text-[14px] text-muted-foreground">
                                {option.hint}
                            </span>
                        </span>
                    </label>
                ))}
                <InputError message={errors['decision']} />
            </fieldset>
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-notes`}>
                    Notas{needs.notes ? ' (obligatorias)' : ' (opcionales)'}
                </Label>
                <Textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    aria-invalid={
                        errors['notes'] === undefined ? undefined : true
                    }
                />
                <InputError message={errors['notes']} />
            </div>
            {needs.plan && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-plan`}>
                            Plan de acción
                        </Label>
                        <Textarea
                            id={`${idPrefix}-plan`}
                            name="action_plan"
                            aria-invalid={
                                errors['action_plan'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError message={errors['action_plan']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-due`}>
                            Fecha compromiso
                        </Label>
                        <DatePicker
                            id={`${idPrefix}-due`}
                            name="plan_due_on"
                            invalid={errors['plan_due_on'] !== undefined}
                        />
                        <InputError message={errors['plan_due_on']} />
                    </div>
                </>
            )}
        </>
    );
}
