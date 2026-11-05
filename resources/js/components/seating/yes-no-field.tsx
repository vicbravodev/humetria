import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';

/** A yes/no question answered with two choice cards; nothing is chosen until the person answers. */
export default function YesNoField({
    name,
    legend,
    value,
    error,
    hint,
    onAnswer,
}: {
    name: string;
    legend: string;
    value: boolean | null;
    error: string | undefined;
    /** A line under the question. */
    hint?: string | undefined;
    onAnswer?: (answer: boolean) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 font-medium">{legend}</legend>
            <div className="grid grid-cols-2 gap-2">
                {[
                    { label: 'Sí', answer: true },
                    { label: 'No', answer: false },
                ].map((choice) => (
                    <label key={choice.label} className={CHOICE_CLASS}>
                        <input
                            type="radio"
                            name={name}
                            value={choice.answer ? '1' : '0'}
                            defaultChecked={value === choice.answer}
                            onChange={() => onAnswer?.(choice.answer)}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {choice.label}
                    </label>
                ))}
            </div>
            {hint !== undefined && (
                <p className="text-sm text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </fieldset>
    );
}
