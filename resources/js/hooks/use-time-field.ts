import type { KeyboardEvent } from 'react';
import { useHeldValue } from '@/hooks/use-held-value';
import { useTypedField } from '@/hooks/use-typed-field';
import { textProblem } from '@/lib/text-problem';
import { maskTimeText, parseTimeText, stepTime } from '@/lib/time-text';

export type TimeFieldProps = {
    defaultValue?: string | undefined;
    /** The time, when the page holds it (HH:mm); without it the field keeps its own. */
    value?: string | undefined;
    onChange?: ((value: string) => void) | undefined;
    /** How far the arrow keys move it, in minutes. */
    step?: number;
};

/** A field's time as typed and as HH:mm, and the arrow keys that move it. */
export function useTimeField({
    defaultValue = '',
    value: held,
    onChange,
    step = 15,
}: TimeFieldProps) {
    const [value, setValue] = useHeldValue(defaultValue, held, onChange);
    const field = useTypedField(value, setValue, {
        read: parseTimeText,
        write: (time) => (parseTimeText(time).kind === 'ok' ? time : ''),
        mask: maskTimeText,
    });

    return {
        value,
        field,
        problem:
            field.problem === undefined
                ? undefined
                : textProblem(field.problem, { kind: 'time' }),
        keys: (event: KeyboardEvent<HTMLInputElement>) => {
            // Enter sends the form: the text goes as HH:mm.
            if (event.key === 'Enter') {
                field.leave();
            }

            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
                field.pick(
                    stepTime(value, event.key === 'ArrowUp' ? step : -step),
                );
            }
        },
    };
}
