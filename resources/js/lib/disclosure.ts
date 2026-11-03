import type { PolicySectionData } from '@/types';

/** The items of a section as one line each, to edit them in a single box. */
export const joinLines = (items: string[]): string => items.join('\n');

/** The lines a box holds, one item each; the server drops the empty ones. */
export const splitLines = (value: string): string[] => value.split('\n');

/** What the form sends for a section: the key, the words and the items; the heading and style are the server's. */
export const sectionPayload = (
    section: PolicySectionData,
): { key: string; text: string; items: string[] } => ({
    key: section.key,
    text: section.text,
    items: section.items,
});

/** The sections after taking one out, when the guide lets it go. */
export const withoutSection = (
    sections: PolicySectionData[],
    key: string,
): PolicySectionData[] =>
    sections.filter((section) => section.key !== key || !section.removable);

/** The sections after changing one of them. */
export const withChange = (
    sections: PolicySectionData[],
    key: string,
    change: Partial<Pick<PolicySectionData, 'text' | 'items'>>,
): PolicySectionData[] =>
    sections.map((section) =>
        section.key === key ? { ...section, ...change } : section,
    );
