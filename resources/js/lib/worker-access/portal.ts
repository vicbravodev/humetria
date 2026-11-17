import type { WorkerSection } from '@/types';

const waits = (section: WorkerSection): boolean =>
    section.pending !== null && section.pending > 0;

/** The person's tools in two groups, each in its order: what waits for their answer, and the rest. */
export function splitSections(sections: WorkerSection[]): {
    waiting: WorkerSection[];
    tools: WorkerSection[];
} {
    return {
        waiting: sections.filter(waits),
        tools: sections.filter((section) => !waits(section)),
    };
}

/** How many things wait for the person across their tools: the count on the «Pendientes» tab. */
export function pendingTotal(sections: WorkerSection[]): number {
    return sections.reduce(
        (total, section) => total + (section.pending ?? 0),
        0,
    );
}

/** The letters of the person's avatar: their given name and their first surname. */
export function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toLocaleUpperCase('es-MX'))
        .join('');
}
