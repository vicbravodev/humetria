function firstLetter(name: string | undefined): string {
    return Array.from(name ?? '')[0] ?? '';
}

/** "María de la Luz Pérez" → "MP": the first letter of the first and last names; one letter for a single name. */
export function initials(fullName: string): string {
    const names = fullName.trim().split(/\s+/u).filter(Boolean);
    const last = names.length > 1 ? firstLetter(names.at(-1)) : '';

    return `${firstLetter(names[0])}${last}`.toUpperCase();
}
