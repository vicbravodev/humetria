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

/**
 * The footer of the page of the people: what the company has yet to publish, by name, and nothing once it is all there (E-20:
 * it used to promise the results under results already published).
 */
export function pendingNotice(
    hasPolicy: boolean,
    hasResults: boolean,
): string | null {
    const missing = [
        hasPolicy ? null : 'la política de prevención',
        hasResults
            ? null
            : 'los resultados de la evaluación y las medidas que toma',
    ].filter((item) => item !== null);

    return missing.length === 0
        ? null
        : `La empresa publicará aquí más información: ${missing.join(', ')}.`;
}
