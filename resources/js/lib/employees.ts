/** How the employees list is narrowed, as the server sends it back: a chosen center or area with its name. */
export type EmployeeFilters = {
    q: string;
    work_center: { value: string; label: string } | null;
    area: { value: string; label: string } | null;
    status: 'active' | 'inactive';
    /** Only the people without email: the survey links go out by email. */
    without_email: boolean;
};

/** What a checked employees CSV would do (ready) or why it cannot be imported (invalid). */
export type EmployeeImportSummary = {
    status: 'ready' | 'invalid';
    rows: number;
    created: number;
    updated: number;
    /** `row` as the spreadsheet numbers it: the header is row 1. */
    errors: { row: number; messages: string[] }[];
};

/** The whole company's people at a glance, whatever the filters. */
export type EmployeeCounts = {
    active: number;
    inactive: number;
    without_email: number;
    work_centers: number;
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
            : { work_center: filters.work_center.value }),
        ...(filters.area === null ? {} : { area: filters.area.value }),
        ...(filters.status === 'inactive' ? { status: 'inactive' } : {}),
        ...(filters.without_email ? { without_email: '1' } : {}),
    };
}

/** A 10-digit mobile as people write it in Mexico: "55 1234 5678". */
export function formatMobile(mobile: string): string {
    const parts = /^(\d{2})(\d{4})(\d{4})$/.exec(mobile);

    return parts === null ? mobile : `${parts[1]} ${parts[2]} ${parts[3]}`;
}

const count = (n: number, one: string, many: string) =>
    `${n} ${n === 1 ? one : many}`;

/** "90 personas activas en 2 centros de trabajo." */
export function headcountText({
    active,
    work_centers,
}: Pick<EmployeeCounts, 'active' | 'work_centers'>): string {
    return active === 0
        ? 'Nadie activo por ahora.'
        : `${count(active, 'persona activa', 'personas activas')} en ${count(work_centers, 'centro de trabajo', 'centros de trabajo')}.`;
}

/** The notice about people no survey link reaches: the fact, then the way to see them. */
export function withoutEmailText(n: number): { fact: string; action: string } {
    return n === 1
        ? { fact: '1 persona no tiene correo', action: 'Ver quién es' }
        : { fact: `${n} personas no tienen correo`, action: `Ver a las ${n}` };
}

/** Where the CSV upload stands: 1 choose the file, 2 review its problems, 3 confirm a file that is ready. */
export function importStep(
    summary: { status: 'ready' | 'invalid' } | undefined,
): 1 | 2 | 3 {
    if (summary === undefined) {
        return 1;
    }

    return summary.status === 'ready' ? 3 : 2;
}

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

/** How one step of the upload looks next to the current one. */
export function importStepState(
    number: number,
    step: number,
): 'done' | 'current' | 'next' {
    if (number === step) {
        return 'current';
    }

    return number < step ? 'done' : 'next';
}

/** The line under the file box: what to drop, a chosen file to check, or how many rows the checked one has. */
export function importFileHint(
    name: string | null,
    rows: number | undefined,
): string {
    if (name === null) {
        return 'Con las columnas de la plantilla, una fila por persona.';
    }

    return rows === undefined
        ? 'Listo para revisar'
        : `${rows.toLocaleString('es-MX')} ${rows === 1 ? 'fila' : 'filas'}`;
}

export type ImportStat = {
    label: string;
    value: number;
    hint?: string;
    alert?: boolean;
};

/** What a ready file will add and update, or, with problems, how many rows to fix. */
export const importStats = (summary: EmployeeImportSummary): ImportStat[] =>
    summary.status === 'ready'
        ? [
              { label: 'Personas nuevas', value: summary.created },
              {
                  label: 'Se actualizan',
                  value: summary.updated,
                  hint: 'Ya existían por su número de empleado o su correo',
              },
              {
                  label: 'Por corregir',
                  value: 0,
                  hint: 'Ninguna fila tiene problemas',
              },
          ]
        : [
              { label: 'Filas en el archivo', value: summary.rows },
              {
                  label: 'Por corregir',
                  value: summary.errors.length,
                  hint: 'No se importa nada hasta corregirlas',
                  alert: true,
              },
          ];
