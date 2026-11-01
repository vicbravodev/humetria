import type { DocumentKindOption } from '@/types';

/** The missing items of a version, as "d) Mecanismos y reglas de contacto…", in the kind's order. */
export function missingLabels(
    kind: DocumentKindOption | undefined,
    missing: string[],
): string[] {
    return (kind?.items ?? [])
        .filter((item) => missing.includes(item.value))
        .map((item) => `${item.value}) ${item.label}`);
}

export function itemLetters(kind: DocumentKindOption | undefined): string[] {
    return (kind?.items ?? []).map((item) => item.value);
}

export {
    addChoice as addCenter,
    removeChoice as removeCenter,
} from '@/lib/telework/choices';
