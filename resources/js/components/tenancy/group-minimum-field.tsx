import { useState } from 'react';
import NumberStepper from '@/components/humetria/number-stepper';
import Notice from '@/components/humetria/notice';
import { lowMinimumWarning, stepMinimum } from '@/lib/group-minimum';

/**
 * The minimum of people per group, moved with − and + or typed (1 to 50). Under the recommended one it says, honestly,
 * what the company gives up; with 1, that a group of one shows that person's answer.
 */
export default function GroupMinimumField({
    minimum,
    recommended,
    lowest,
    highest,
    error,
}: {
    minimum: number;
    recommended: number;
    lowest: number;
    highest: number;
    error: string | undefined;
}) {
    const [value, setValue] = useState(String(minimum));
    const typed = Number(value);
    const warning =
        value.trim() !== '' && Number.isInteger(typed) && typed >= lowest
            ? lowMinimumWarning(typed, recommended)
            : null;

    return (
        <div className="flex flex-col gap-4">
            <div className="max-w-72">
                <NumberStepper
                    id="min-group"
                    name="min_group"
                    label="Mínimo de personas por grupo"
                    value={value}
                    suffix="personas"
                    hint={`De ${lowest} a ${highest}. Recomendamos ${recommended}.`}
                    error={error}
                    onChange={setValue}
                    onStep={(direction) =>
                        setValue(
                            stepMinimum(value, direction, { lowest, highest }),
                        )
                    }
                />
            </div>
            {warning !== null && <Notice>{warning}</Notice>}
        </div>
    );
}
