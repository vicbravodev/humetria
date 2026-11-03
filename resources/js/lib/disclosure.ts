import type { Option } from '@/lib/option-search';
import { countOf } from '@/lib/plural';
import type {
    BadgeVariant,
    BoardSummary,
    PolicySectionData,
    RequirementValue,
} from '@/types';

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

/** How a state of the board reads as a badge: what is in order, what to look at, what is missing, what does not apply. */
export const requirementVariant = (state: RequirementValue): BadgeVariant => {
    switch (state) {
        case 'met':
            return 'success';
        case 'notice':
            return 'warning';
        case 'missing':
            return 'destructive';
        case 'not_applicable':
            return 'outline';
    }
};

/** One sentence for the whole board: what is missing first, since it is what RH looks for, then what is in order. */
export const boardHeadline = (summary: BoardSummary): string => {
    const total = summary.met + summary.notice + summary.missing;

    if (total === 0) {
        return 'Aún no hay centros de trabajo.';
    }

    if (summary.missing === 0 && summary.notice === 0) {
        return total === 1
            ? 'Tu centro tiene registrado todo lo que revisamos.'
            : `Los ${total} centros tienen registrado todo lo que revisamos.`;
    }

    const parts = [
        { count: summary.missing, text: 'con faltantes' },
        { count: summary.notice, text: 'con avisos' },
        { count: summary.met, text: 'al día' },
    ].filter(({ count }) => count > 0);
    const sentences = parts.map(({ count, text }, index) =>
        index === 0
            ? `${countOf(count, 'centro', 'centros')} ${text}`
            : `${count} ${text}`,
    );
    const last = sentences.pop();

    return `${sentences.length === 0 ? '' : `${sentences.join(', ')} y `}${last}.`;
};

/** The centers chosen after adding one: once each, in the order they were added. */
export const addCenter = (chosen: Option[], center: Option): Option[] =>
    chosen.some((option) => option.value === center.value)
        ? chosen
        : [...chosen, center];

/** The centers chosen after taking one out. */
export const removeCenter = (chosen: Option[], value: string): Option[] =>
    chosen.filter((option) => option.value !== value);

/** The incisos of numeral 5.1 a policy contemplates, as the norm words them. */
export const INCISOS: { value: string; label: string }[] = [
    {
        value: 'a',
        label: 'La prevención de los factores de riesgo psicosocial',
    },
    { value: 'b', label: 'La prevención de la violencia laboral' },
    {
        value: 'c',
        label: 'La promoción de un entorno organizacional favorable',
    },
];
