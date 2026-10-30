/** The chosen ids with `id` added (once) or taken out. */
export function toggleChosen(
    chosen: string[],
    id: string,
    checked: boolean,
): string[] {
    if (!checked) {
        return chosen.filter((other) => other !== id);
    }

    return chosen.includes(id) ? chosen : [...chosen, id];
}

/** The chosen ids plus every one of `ids` not chosen yet. */
export function chooseAll(chosen: string[], ids: string[]): string[] {
    const already = new Set(chosen);

    return [...chosen, ...ids.filter((id) => !already.has(id))];
}

/** The strings of a list the server sent; anything else is no list. */
export function idList(value: unknown): string[] {
    return Array.isArray(value)
        ? value.filter((id): id is string => typeof id === 'string')
        : [];
}

/** The chosen people whose name was seen (in order), and how many were chosen without it (all a search found). */
export function chosenNames(
    chosen: string[],
    names: Record<string, string>,
): { named: { id: string; name: string }[]; unnamed: number } {
    const named = chosen.flatMap((id) => {
        const name = names[id];

        return name === undefined ? [] : [{ id, name }];
    });

    return { named, unnamed: chosen.length - named.length };
}
