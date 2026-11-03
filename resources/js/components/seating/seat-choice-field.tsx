import InputError from '@/components/humetria/input-error';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { SeatOption } from '@/types';

/** One value out of a short fixed list, as choice cards (kind, use, condition of a seat). */
export default function SeatChoiceField({
    name,
    legend,
    options,
    value,
    error,
}: {
    name: string;
    legend: string;
    options: SeatOption[];
    value: string | undefined;
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 font-medium">{legend}</legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <label key={option.value} className={CHOICE_CLASS}>
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            defaultChecked={value === option.value}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
