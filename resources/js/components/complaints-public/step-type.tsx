import ChoiceCard from '@/components/complaints-public/choice-card';
import type { ComplaintTypeOption } from '@/types';

/** Step 1 of 4: what the person wants to report. */
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
            <legend className="mb-1 text-[22px] leading-7 font-semibold">
                ¿Qué quiere reportar?
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
