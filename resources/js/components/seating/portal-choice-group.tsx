import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { PortalCondition } from '@/types';

/** One condition of the DOF as three choice cards, each with its own words and nothing pre-chosen. */
export default function PortalChoiceGroup({
    name,
    condition,
    error,
}: {
    name: string;
    condition: PortalCondition;
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-lg font-semibold">
                {condition.title}
            </legend>
            {Object.entries(condition.options).map(([points, text]) => (
                <label key={points} className={CHOICE_CLASS}>
                    <input
                        type="radio"
                        name={name}
                        value={points}
                        className="size-5 shrink-0 accent-primary"
                    />
                    {text}
                </label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}
