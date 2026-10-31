import type { Option } from '@/lib/option-search';
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

/** The work centers a document is being published to: each once, never the "all" choice of a filter. */
export function addCenter(chosen: Option[], center: Option): Option[] {
    return center.value === '' ||
        chosen.some((option) => option.value === center.value)
        ? chosen
        : [...chosen, center];
}

export function removeCenter(chosen: Option[], value: string): Option[] {
    return chosen.filter((option) => option.value !== value);
}
