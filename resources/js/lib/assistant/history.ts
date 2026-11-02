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

/** Hoy, Ayer, Esta semana (the last six days) or Anteriores, by the day in Mexico City. */
export function dayLabel(updatedAt: string, now: Date): string {
    const age = dayNumber(now) - dayNumber(new Date(updatedAt));

    if (age <= 0) {
        return 'Hoy';
    }

    if (age === 1) {
        return 'Ayer';
    }

    return age < 7 ? 'Esta semana' : 'Anteriores';
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

    return { id, role, parts: [{ type: 'text', text: content }] };
}

/** The messages of a conversation as the chat shows them, from what the server answers (latest first): oldest first, text only. */
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
