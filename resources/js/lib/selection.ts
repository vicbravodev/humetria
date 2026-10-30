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
