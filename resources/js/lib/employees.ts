/** How the employees list is narrowed, as the server sends it back. */
export type EmployeeFilters = {
    q: string;
    work_center: string | null;
    area: string | null;
    status: 'active' | 'inactive';
};

/** The query string for the filters: only what narrows the list, so a plain list has a plain address. */
export function employeeQuery(
    filters: EmployeeFilters,
): Partial<Record<keyof EmployeeFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.work_center === null
            ? {}
            : { work_center: filters.work_center }),
        ...(filters.area === null ? {} : { area: filters.area }),
        ...(filters.status === 'inactive' ? { status: 'inactive' } : {}),
    };
}

/** A 10-digit mobile as people write it in Mexico: "55 1234 5678". */
export function formatMobile(mobile: string): string {
    const parts = /^(\d{2})(\d{4})(\d{4})$/.exec(mobile);

    return parts === null ? mobile : `${parts[1]} ${parts[2]} ${parts[3]}`;
}

const count = (n: number, one: string, many: string) =>
    `${n} ${n === 1 ? one : many}`;

/** What a checked file is ready to do: "Listo para importar 3 empleados: 1 nuevo y 2 actualizados." */
export function importSummaryText(summary: {
    rows: number;
    created: number;
    updated: number;
}): string {
    return `Listo para importar ${count(summary.rows, 'empleado', 'empleados')}: ${count(summary.created, 'nuevo', 'nuevos')} y ${count(summary.updated, 'actualizado', 'actualizados')}.`;
}

export function importButtonText(rows: number): string {
    return `Importar ${count(rows, 'empleado', 'empleados')}`;
}
