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

export function viewOf(message: UIMessage): MessageView {
    const steps: MessageStep[] = [];
    const items: MessageItem[] = [];

    for (const part of message.parts) {
        if (part.type === 'text') {
            items.push({ kind: 'text', text: part.text });
        } else if (isToolUIPart(part)) {
            const done = part.state === 'output-available';
            const card = done ? parseToolCard(part.output) : null;

            steps.push({ id: part.toolCallId, tool: getToolName(part), done });

            if (card !== null) {
                items.push({ kind: 'card', card });
            }
        }
    }

    return {
        steps,
        items,
        text: items
            .flatMap((item) => (item.kind === 'text' ? item.text : []))
            .join(''),
        renders: items.flatMap((item) =>
            item.kind === 'card' ? item.card.render : [],
        ),
    };
}
