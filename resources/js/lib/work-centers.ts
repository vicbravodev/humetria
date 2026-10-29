/** How many people work in a work center: "Sin personal", "1 persona", "48 personas". */
export function staffText(employees: number): string {
    if (employees === 0) {
        return 'Sin personal';
    }

    return employees === 1 ? '1 persona' : `${employees} personas`;
}

/** The NOM-035 guides that apply, written for display; null when none does (nobody works there yet). */
export function guidesText(guides: string[]): string | null {
    if (guides.length === 0) {
        return null;
    }

    return guides.length === 1
        ? `NOM-035: Guía ${guides.join('')}`
        : `NOM-035: Guías ${guides.slice(0, -1).join(', ')} y ${guides.at(-1) ?? ''}`;
}
