/** Something the ⌘K palette can do: its label and other words a person might type to find it. */
export type Command = {
    id: string;
    label: string;
    keywords?: string[];
};

/** Text as a search compares it: without accents, in lowercase and trimmed. */
export const fold = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .trim();

/** The commands whose label or keywords contain what was typed, ignoring case and accents; all of them for nothing typed. */
export function matchCommands<T extends Command>(
    commands: T[],
    query: string,
): T[] {
    const typed = fold(query);

    if (typed === '') {
        return commands;
    }

    return commands.filter(({ label, keywords = [] }) =>
        [label, ...keywords].some((text) => fold(text).includes(typed)),
    );
}

const MOVES: Record<string, (active: number, last: number) => number> = {
    ArrowDown: (active) => active + 1,
    ArrowUp: (active) => active - 1,
    Home: () => 0,
    End: (_, last) => last,
};

/** Where the active option goes for a key in a list of `count`, kept inside it; undefined for keys that do not move it. */
export function moveActive(
    key: string,
    active: number,
    count: number,
): number | undefined {
    const last = Math.max(count - 1, 0);
    const move = MOVES[key];

    return move === undefined
        ? undefined
        : Math.min(Math.max(move(active, last), 0), last);
}
