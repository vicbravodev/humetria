import type { UIMessage } from 'ai';
import type { ChoiceRow } from '@/lib/assistant/tool-cards';

/** How the buttons of a question stand: whether they can be pressed, which one the person pressed, and what a press does. */
export type ChoiceState = {
    disabled: boolean;
    chosen: string | null;
    onPick: (text: string) => void;
};

/** The text of the option the person answered with, when what they sent next is exactly that text; nothing otherwise. */
export function chosenChoice(
    rows: readonly ChoiceRow[],
    next: string | null,
): string | null {
    return rows.find((row) => row.text === next)?.text ?? null;
}

/** What the person wrote in a message of theirs; null for no message or for an answer of Humi. */
export function textOfUser(message: UIMessage | undefined): string | null {
    return message === undefined || message.role !== 'user'
        ? null
        : message.parts
              .map((part) => (part.type === 'text' ? part.text : ''))
              .join('');
}

/**
 * The buttons are live only on the last answer, once it has finished arriving; any other, or one still arriving, is a
 * record. The one pressed is the option whose text the person sent in the message that follows.
 */
export function choiceStateOf({
    rows,
    last,
    streaming,
    next,
    onPick,
}: {
    rows: readonly ChoiceRow[];
    last: boolean;
    streaming: boolean;
    next: UIMessage | undefined;
    onPick: (text: string) => void;
}): ChoiceState {
    return {
        disabled: !last || streaming,
        chosen: chosenChoice(rows, textOfUser(next)),
        onPick,
    };
}
