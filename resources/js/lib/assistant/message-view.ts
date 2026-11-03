import type { UIMessage } from 'ai';
import { getToolName, isToolUIPart } from '@/lib/assistant/ai-client';
import { parseToolCard, type ToolCard } from '@/lib/assistant/tool-cards';

export type MessageStep = { id: string; tool: string; done: boolean };

export type MessageItem =
    | { kind: 'text'; text: string }
    | { kind: 'card'; card: ToolCard };

/** An answer of Humi ready to draw: the lines of what it looked at, then its text and cards in the order they came. */
export type MessageView = {
    steps: MessageStep[];
    items: MessageItem[];
    /** All its text, for copying. */
    text: string;
    /** Which cards it carries, in order, to offer questions that follow. */
    renders: string[];
};

/** One line per tool, done only when every call of it is: asking the same tool twice is not two things looked at. */
function mergedSteps(steps: readonly MessageStep[]): MessageStep[] {
    return steps.reduce<MessageStep[]>((merged, step) => {
        const same = merged.find((other) => other.tool === step.tool);

        if (same === undefined) {
            return [...merged, step];
        }

        same.done = same.done && step.done;

        return merged;
    }, []);
}

type ListCard = Extract<ToolCard, { render: 'list' }>;

function criteriaOf(card: ToolCard): card is ListCard {
    return (
        card.render === 'list' &&
        card.rows.length > 0 &&
        card.rows.every((row) => row.ref !== null)
    );
}

/**
 * The card to draw for what a tool answered. What Humi looks up in the norms, however many times, is one card with each
 * criterion once: a criterion already drawn is not drawn again, and a card with nothing new adds nothing.
 */
function addCard(items: MessageItem[], card: ToolCard, seen: Set<string>) {
    if (!criteriaOf(card)) {
        items.push({ kind: 'card', card });

        return;
    }

    const rows = card.rows.filter((row) => !seen.has(row.ref ?? ''));

    for (const row of rows) {
        seen.add(row.ref ?? '');
    }

    const at = items.findIndex(
        (item) => item.kind === 'card' && criteriaOf(item.card),
    );
    const first = items[at];

    if (first?.kind === 'card' && criteriaOf(first.card)) {
        items[at] = {
            kind: 'card',
            card: { ...first.card, rows: [...first.card.rows, ...rows] },
        };
    } else if (rows.length > 0) {
        items.push({ kind: 'card', card: { ...card, rows } });
    }
}

export function viewOf(message: UIMessage): MessageView {
    const steps: MessageStep[] = [];
    const items: MessageItem[] = [];
    const seen = new Set<string>();

    for (const part of message.parts) {
        if (part.type === 'text') {
            items.push({ kind: 'text', text: part.text });
        } else if (isToolUIPart(part)) {
            const done = part.state === 'output-available';
            const card = done ? parseToolCard(part.output) : null;

            steps.push({ id: part.toolCallId, tool: getToolName(part), done });

            if (card !== null) {
                addCard(items, card, seen);
            }
        }
    }

    return {
        steps: mergedSteps(steps),
        items,
        text: items
            .flatMap((item) => (item.kind === 'text' ? item.text : []))
            .join(''),
        renders: items.flatMap((item) =>
            item.kind === 'card' ? item.card.render : [],
        ),
    };
}

/**
 * Whether the person is still waiting for the first sign of the answer: the SDK adds Humi's message, empty, the moment the
 * stream opens, so an answer with no step and no word yet is the same wait as no answer at all.
 */
export function isWaiting(
    messages: readonly UIMessage[],
    streaming: boolean,
): boolean {
    const last = messages.at(-1);

    if (!streaming) {
        return false;
    }

    if (last === undefined || last.role === 'user') {
        return true;
    }

    const view = viewOf(last);

    return view.items.length === 0 && view.steps.length === 0;
}

/** The messages to draw: the empty answer of the wait is left out, the wait itself says Humi is on it. */
export function shownMessages(
    messages: readonly UIMessage[],
    waiting: boolean,
): readonly UIMessage[] {
    return waiting && messages.at(-1)?.role === 'assistant'
        ? messages.slice(0, -1)
        : messages;
}
