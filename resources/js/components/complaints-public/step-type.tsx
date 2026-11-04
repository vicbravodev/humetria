import ChoiceCard from '@/components/complaints-public/choice-card';
import type { ComplaintTypeOption } from '@/types';

/** Step 1: what the person wants to report. */
export default function StepType({
    types,
    value,
    onChange,
}: {
    types: ComplaintTypeOption[];
    value: string;
    onChange: (type: string) => void;
}) {
    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-1 text-xl font-semibold">
                ¿Qué quiere informar?
            </legend>
            {types.map((type) => (
                <ChoiceCard
                    key={type.value}
                    name="type"
                    value={type.value}
                    checked={value === type.value}
                    onSelect={onChange}
                >
                    <span className="font-medium">{type.label}</span>
                    <span className="text-base text-muted-foreground">
                        {type.hint}
                    </span>
                </ChoiceCard>
            ))}
        </fieldset>
    );
}
