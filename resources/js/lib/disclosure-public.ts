import type { PolicyTextSection } from '@/types';

const HIGHLIGHTS = 3;

/** A commitment as a sentence of its own: the «;» or «, y» that chained it to the next one becomes a period. */
function sentence(item: string): string {
    return `${item.trim().replace(/(?:;|,\s*y|\.)$/u, '')}.`;
}

/**
 * The first commitments of a policy, for its card on the page of the people (board V4-Pagina-Personas): the items of its first
 * list or, written with questions, its paragraphs numbered «1.».
 */
export function policyHighlights(sections: PolicyTextSection[]): string[] {
    const listed = sections.find(({ items }) => items.length > 0);
    const items =
        listed?.items ??
        sections
            .flatMap(({ text }) => text.split(/\n\s*\n/))
            .map((paragraph) => paragraph.trim())
            .filter((paragraph) => /^\d+\.\s/u.test(paragraph))
            .map((paragraph) => paragraph.replace(/^\d+\.\s+/u, ''));

    return items.slice(0, HIGHLIGHTS).map(sentence);
}
