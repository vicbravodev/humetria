import type { UIMessage } from 'ai';
import { isToolUIPart } from '@/lib/assistant/ai-client';

/** Where an answer leads in the panel: a button to the exact page («Ver la campaña»). */
export type NextStep = { label: string; link: string };

/** What an answer can point to beyond its text, only when a tool brought it: Humi never makes up a source or a link. */
export type AnswerLinks = { next: NextStep | null; sources: string[] };

function payloadOf(output: unknown): object | null {
    let value = output;

    if (typeof output === 'string') {
        try {
            value = JSON.parse(output);
        } catch {
            return null;
        }
    }

    return typeof value === 'object' && value !== null ? value : null;
}

/** A next step of the panel itself: a path of this site, never another one. */
function nextOf(payload: object): NextStep | null {
    const next = 'next' in payload ? payload.next : null;

    if (typeof next !== 'object' || next === null) {
        return null;
    }

    const label = 'label' in next ? next.label : null;
    const link = 'link' in next ? next.link : null;

    return typeof label === 'string' &&
        label !== '' &&
        typeof link === 'string' &&
        /^\/(?!\/)/.test(link)
        ? { label, link }
        : null;
}

function sourcesOf(payload: object): string[] {
    const sources = 'sources' in payload ? payload.sources : null;

    return Array.isArray(sources)
        ? sources.filter(
              (source): source is string => typeof source === 'string',
          )
        : [];
}

/** The next step (the first a tool brought) and the sources (each once, in order) of an answer. */
export function answerLinksOf(message: UIMessage): AnswerLinks {
    const payloads = message.parts.flatMap((part) =>
        isToolUIPart(part) && part.state === 'output-available'
            ? (payloadOf(part.output) ?? [])
            : [],
    );

    return {
        next: payloads.map(nextOf).find((next) => next !== null) ?? null,
        sources: [...new Set(payloads.flatMap(sourcesOf))],
    };
}
