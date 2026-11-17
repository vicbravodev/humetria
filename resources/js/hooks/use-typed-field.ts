import { useState } from 'react';
import type { TextReading } from '@/lib/date-text';
import type { TextProblem } from '@/lib/text-problem';

type Shape = {
    /** Reads what was typed as the value the server takes. */
    read: (text: string) => TextReading;
    /** Writes a value the way the field shows it ("07/10/2018"). */
    write: (value: string) => string;
    /** Writes a run of digits with its separators. */
    mask: (text: string) => string;
    /** Takes the text only when the field is left (or Enter is pressed): for a filter that asks the server. */
    onLeave?: boolean;
};

/**
 * A text field for a value with one shape (a day, a month, a time): what the person types stays as typed while they
 * write, the value follows it as soon as it reads as one (and is empty while it does not; or, `onLeave`, only when the
 * field is left), and leaving the field writes it the field's way. Whatever is left unreadable says why once the person
 * leaves. The value is told only when it changes.
 */
export function useTypedField(
    value: string,
    setValue: (value: string) => void,
    { read, write, mask, onLeave = false }: Shape,
) {
    const [draft, setDraft] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    const text = draft ?? write(value);
    const reading = draft === null ? undefined : read(draft);
    const change = (next: string) => {
        if (next !== value) {
            setValue(next);
        }
    };
    const problem: TextProblem | undefined =
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
            const masked = mask(next);
            const typed = read(masked);

            setDraft(masked);

            if (!onLeave) {
                change(typed.kind === 'ok' ? typed.value : '');
            }
        },
        focus: () => setEditing(true),
        leave: () => {
            setEditing(false);

            if (reading?.kind === 'ok' || reading?.kind === 'empty') {
                setDraft(null);
                change(reading.kind === 'ok' ? reading.value : '');
            }
        },
        /** Drops whatever was typed, for a value the page sets itself (both ends of a period at once). */
        reset: () => setDraft(null),
        /** Sets a value picked elsewhere (a calendar, a shortcut, «Borrar»), dropping whatever was typed. */
        pick: (picked: string) => {
            setDraft(null);
            change(picked);
        },
    };
}
