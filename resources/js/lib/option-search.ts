import { fold } from '@/lib/commands';

/** One choice of a Combobox: what the form sends and what the person reads. */
export type Option = { value: string; label: string };

/** Options with their label already folded, so typing does not fold every label again on each key. */
export type OptionIndex = { option: Option; text: string }[];

export type SearchState = {
    status: 'idle' | 'loading' | 'done' | 'error';
    options: Option[];
};

export function indexOptions(options: Option[]): OptionIndex {
    return options.map((option) => ({ option, text: fold(option.label) }));
}

/** 0: the label starts with it; 1: one of its words does; 2: it is somewhere inside; undefined: not there. */
function rank(text: string, typed: string): number | undefined {
    if (text.startsWith(typed)) {
        return 0;
    }

    if (text.includes(` ${typed}`)) {
        return 1;
    }

    return text.includes(typed) ? 2 : undefined;
}

/**
 * The options whose label contains what was typed, ignoring case and accents, the closest first and the given
 * order kept inside each group; at most `limit`, with `more` telling whether some were left out.
 */
export function filterOptions(
    index: OptionIndex,
    query: string,
    limit: number,
): { options: Option[]; more: boolean } {
    const typed = fold(query);
    const groups: Option[][] = [[], [], []];

    for (const { option, text } of index) {
        const group = typed === '' ? 0 : rank(text, typed);

        if (group !== undefined) {
            groups[group]?.push(option);
        }
    }

    const found = groups.flat();

    return { options: found.slice(0, limit), more: found.length > limit };
}

type NoteInput = {
    status: SearchState['status'];
    /** How many options are on screen. */
    found: number;
    more: boolean;
    minLength: number;
};

/** The line a Combobox shows under its options, if any: what is happening or what to do next. */
export function searchNote({
    status,
    found,
    more,
    minLength,
}: NoteInput): string | null {
    const notes: Record<SearchState['status'], string | null> = {
        loading: 'Buscando…',
        error: 'No se pudo buscar. Intenta de nuevo.',
        idle: `Escribe al menos ${minLength} letras para buscar.`,
        done: null,
    };

    if (status !== 'done') {
        return notes[status];
    }

    if (found === 0) {
        return 'Sin resultados';
    }

    return more ? 'Escribe para encontrar más.' : null;
}
