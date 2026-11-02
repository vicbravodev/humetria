/** The singular for exactly one, the plural for anything else (zero included). */
export function plural(count: number, one: string, many: string): string {
    return count === 1 ? one : many;
}

/** "1 persona", "12 personas": a count with its noun in the right number. */
export function countOf(count: number, one: string, many: string): string {
    return `${count} ${plural(count, one, many)}`;
}
