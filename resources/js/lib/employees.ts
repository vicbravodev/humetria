import { countOf } from '@/lib/plural';
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
    /** Who is updated and what changes in them (the first hundred); absent when nobody changes. */
    changes?: EmployeeImportChange[];
    /** Rows that import but leave something incomplete (the first hundred); absent when there are none. */
    warnings?: { row: number; messages: string[] }[];
};

/** What a file changes in one person who already exists: each field with its before and after. */
export type EmployeeImportChange = {
    row: number;
    name: string;
    changes: { label: string; before: string; after: string }[];
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

/** "90 personas activas en 2 centros de trabajo." */
export function headcountText({
    active,
    work_centers,
}: Pick<EmployeeCounts, 'active' | 'work_centers'>): string {
    return active === 0
        ? 'Nadie activo por ahora.'
        : `${countOf(active, 'persona activa', 'personas activas')} en ${countOf(work_centers, 'centro de trabajo', 'centros de trabajo')}.`;
}

/** "90 personas activas." for a union, whose people have no work center. */
export function unionHeadcountText(active: number): string {
    return active === 0
        ? 'Nadie activo por ahora.'
        : `${countOf(active, 'persona activa', 'personas activas')}.`;
}

/**
 * The line under a person's name: the position, the company ID (not again when the ID is what they are shown by) and,
 * for someone deactivated, since when.
 */
export function employeeDetails(employee: {
    name: string;
    job_title: string;
    employee_number: string | null;
    deactivated_on: string | null;
}): string {
    return [
        employee.job_title,
        employee.employee_number === employee.name
            ? null
            : employee.employee_number,
        employee.deactivated_on === null
            ? null
            : `Baja el ${employee.deactivated_on}`,
    ]
        .filter((part) => part !== null && part !== '')
        .join(' · ');
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
    return `Listo para importar ${countOf(summary.rows, 'empleado', 'empleados')}: ${countOf(summary.created, 'nuevo', 'nuevos')} y ${countOf(summary.updated, 'actualizado', 'actualizados')}.`;
}

export function importButtonText(rows: number): string {
    return `Importar ${countOf(rows, 'empleado', 'empleados')}`;
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

/** The closed list of reasons a work relationship ends, as the server validates them. */
export const deactivationReasons = [
    { value: 'resignation', label: 'Renuncia' },
    { value: 'dismissal', label: 'Rescisión o despido' },
    { value: 'contract_end', label: 'Término de contrato' },
    { value: 'mutual_agreement', label: 'Mutuo acuerdo' },
    { value: 'death', label: 'Fallecimiento' },
    { value: 'other', label: 'Otro' },
] as const;

/** Today as people in Mexico live it (`AAAA-MM-DD`): what a departure date may not pass. */
export function localToday(now: Date = new Date()): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Mexico_City',
    }).format(now);
}

/** «Celular: 5511111111 → 5522222222», one change of a person. */
export function changeLine(change: {
    label: string;
    before: string;
    after: string;
}): string {
    return `${change.label}: ${change.before} → ${change.after}`;
}
