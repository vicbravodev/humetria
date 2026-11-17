import { useState } from 'react';
import type { TextReading } from '@/lib/date-text';

type Shape = {
    /** Reads what was typed as the value the server takes. */
    read: (text: string) => TextReading;
    /** Writes a value the way the field shows it ("07/10/2018"). */
    write: (value: string) => string;
    /** Adds the separators while the text is typed. */
    mask: (next: string, previous: string) => string;
};

/** Why the text left in a field is not taken: it is no value, or it is outside the first and last allowed. */
export type TypedProblem = 'invalid' | 'early' | 'late';

/**
 * A text field for a value with one shape (a day, a month, a time): what the person types stays as typed while they
 * write, the value follows it as soon as it reads as one (and is empty while it does not), and leaving the field writes
 * it the field's way. Whatever is left unreadable says why once the person leaves.
 */
export function useTypedField(
    value: string,
    setValue: (value: string) => void,
    { read, write, mask }: Shape,
) {
    const [draft, setDraft] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    const text = draft ?? write(value);
    const reading = draft === null ? undefined : read(draft);
    const problem: TypedProblem | undefined =
        editing ||
        reading === undefined ||
        reading.kind === 'ok' ||
        reading.kind === 'empty'
            ? undefined
            : reading.kind;

    return {
        text,
        problem,
        type: (next: string) => {
            const masked = mask(next, text);
            const typed = read(masked);

            setDraft(masked);
            setValue(typed.kind === 'ok' ? typed.value : '');
        },
        focus: () => setEditing(true),
        leave: () => {
            setEditing(false);

            if (reading?.kind === 'ok' || reading?.kind === 'empty') {
                setDraft(null);
            }
        },
        /** Sets a value picked elsewhere (a calendar, a shortcut, «Borrar»), dropping whatever was typed. */
        pick: (picked: string) => {
            setDraft(null);
            setValue(picked);
        },
    };
}
