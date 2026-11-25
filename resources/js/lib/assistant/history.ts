import type { UIMessage } from 'ai';
import type { ConversationRow } from '@/types';

const ZONE = 'America/Mexico_City';

/** The calendar day of an instant in Mexico City, as the number of days since 1970 (so days can be told apart and counted). */
function dayNumber(instant: Date): number {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(instant);
    const [year = 0, month = 1, day = 1] = parts.split('-').map(Number);

    return Math.floor(Date.UTC(year, month - 1, day) / 86_400_000);
}

/** Hoy, Esta semana (the six days before) or Antes, by the day in Mexico City. */
export function dayLabel(updatedAt: string, now: Date): string {
    const age = dayNumber(now) - dayNumber(new Date(updatedAt));

    if (age <= 0) {
        return 'Hoy';
    }

    return age < 7 ? 'Esta semana' : 'Antes';
}

/** The query of the history page: the search only when there is one, so a plain page keeps a plain address. */
export function historyQuery(filters: { q: string }): { q?: string } {
    return filters.q === '' ? {} : { q: filters.q };
}

export type DayGroup = { label: string; rows: ConversationRow[] };

/** The history in groups by day, in the order it came (newest first). */
export function groupByDay(
    rows: readonly ConversationRow[],
    now: Date,
): DayGroup[] {
    return rows.reduce<DayGroup[]>((groups, row) => {
        const label = dayLabel(row.updated_at, now);
        const last = groups.at(-1);

        return last?.label === label
            ? [...groups.slice(0, -1), { label, rows: [...last.rows, row] }]
            : [...groups, { label, rows: [row] }];
    }, []);
}

/** The card of the buttons a turn ended with, as the JSON text the server keeps, when it ended asking. */
function choicesOf(item: object): string | null {
    const choices = 'choices' in item ? item.choices : null;

    return typeof choices === 'string' ? choices : null;
}

/** A turn that ended asking has no text: it brings the card of its buttons, drawn as the stream would have drawn it. */
function partsOf(
    id: string,
    content: string,
    choices: string | null,
): UIMessage['parts'] {
    if (choices === null) {
        return [{ type: 'text', text: content }];
    }

    const buttons: UIMessage['parts'] = [
        {
            type: 'tool-OfferChoices',
            toolCallId: `${id}-choices`,
            state: 'output-available',
            input: {},
            output: choices,
        },
    ];

    return content === ''
        ? buttons
        : [{ type: 'text', text: content }, ...buttons];
}

function messageOf(item: unknown): UIMessage | null {
    if (
        typeof item !== 'object' ||
        item === null ||
        !('id' in item) ||
        !('role' in item) ||
        !('content' in item)
    ) {
        return null;
    }

    const { id, role, content } = item;

    if (
        typeof id !== 'string' ||
        typeof content !== 'string' ||
        (role !== 'user' && role !== 'assistant')
    ) {
        return null;
    }

    return { id, role, parts: partsOf(id, content, choicesOf(item)) };
}

/** The messages of a conversation as the chat shows them, from what the server answers (latest first): oldest first, their text and the buttons of a question. */
export function uiMessagesFrom(body: unknown): UIMessage[] {
    if (
        typeof body !== 'object' ||
        body === null ||
        !('messages' in body) ||
        !Array.isArray(body.messages)
    ) {
        return [];
    }

    return body.messages
        .flatMap((item: unknown) => messageOf(item) ?? [])
        .reverse();
}
