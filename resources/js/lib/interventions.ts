import { index } from '@/routes/tenant/campaigns';
import { route } from '@/routes/tenant/nom035';
import type {
    ActionStatusValue,
    BadgeVariant,
    ProgramPageProps,
    ProgramTools,
    BreadcrumbItem,
    ProgramAction,
    ProgramCampaign,
    ProgramRequirement,
    ProgramScope,
    ProgramStateValue,
    Requirement,
} from '@/types';

/** What an evaluation without a Programa tells: the title and the sentence (num. 8.3). */
export function programStartText(requirement: ProgramRequirement): {
    title: string;
    body: string;
} {
    switch (requirement) {
        case 'required':
            return {
                title: 'Esta evaluación pide un Programa de intervención',
                body: 'Hay cuestionarios en riesgo medio o más. El Programa parte de ese resultado: una fila por cada categoría y dominio que lo pide.',
            };
        case 'below_minimum':
            return {
                title: 'Hubo muy pocos cuestionarios para mostrar cifras',
                body: 'Por la confidencialidad no se deriva ninguna fila del resultado. Puedes crear el Programa vacío y agregar tú las filas que quieras atender.',
            };
        case 'not_required':
            return {
                title: 'La norma no pide un Programa para esta evaluación',
                body: 'Ningún cuestionario quedó en riesgo medio o más (num. 8.3). Si cambia el resultado, aquí aparecerá la opción de crearlo.',
            };
    }
}

/** The name of what a row attends, by its scope. */
export const SCOPE_LABELS: Record<ProgramScope, string> = {
    final: 'Calificación final',
    category: 'Categoría',
    domain: 'Dominio',
};

/** The trail of the Programa: NOM-035 › the work center › Programa de intervención. */
export function programTrail(
    tenantSlug: string,
    campaign: ProgramCampaign,
): BreadcrumbItem[] {
    return [
        { title: 'NOM-035', href: index(tenantSlug) },
        {
            title: campaign.workCenter,
            href: route([tenantSlug, campaign.workCenterId]),
        },
        { title: 'Programa de intervención' },
    ];
}

export type ProgramTab = 'rows' | 'actions' | 'follow-up';

const TABS: ProgramTab[] = ['rows', 'actions', 'follow-up'];

/** The tab of the page, from its query string (`?tab=actions`); the rows without one. */
export function activeTab(search: string): ProgramTab {
    const tab = new URLSearchParams(search).get('tab');

    return TABS.find((candidate) => candidate === tab) ?? 'rows';
}

/** The address of a tab: the filters and pages of the others stay out of it. */
export function tabHref(path: string, tab: ProgramTab): string {
    return tab === 'rows' ? path : `${path}?tab=${tab}`;
}

/** How a state of an action reads in a list. */
export function statusVariant(status: ActionStatusValue): BadgeVariant {
    switch (status) {
        case 'in_progress':
            return 'default';
        case 'done':
            return 'success';
        case 'cancelled':
            return 'secondary';
        case 'planned':
            return 'outline';
    }
}

/** How the state of a Programa reads in a header or a list. */
export function stateVariant(state: ProgramStateValue): BadgeVariant {
    switch (state) {
        case 'none':
            return 'secondary';
        case 'in_progress':
            return 'default';
        case 'overdue':
            return 'destructive';
        case 'done':
            return 'success';
    }
}

/** What the meter counts: "3 de 8 acciones hechas"; nothing yet reads as such. */
export function meterText(meter: { done: number; total: number }): string {
    return meter.total === 0
        ? 'Aún no hay acciones'
        : `${meter.done} de ${meter.total} ${meter.total === 1 ? 'acción hecha' : 'acciones hechas'}`;
}

/** Who an action is for: the whole center, or the areas shown (and how many more stay hidden). */
export function subjectsText(action: ProgramAction): string {
    if (action.subjects.value === 'center') {
        return action.subjects.label;
    }

    const names = action.areas.map((area) => area.name).join(', ');
    const hidden =
        action.hiddenAreas === 0 ? '' : ` y ${action.hiddenAreas} más`;

    return `${names}${hidden}`;
}

/** The icon-less sentence for a line of «Lo que pide la Tabla». */
export function requirementText(line: Requirement): string {
    switch (line.status) {
        case 'covered':
            return line.detail === null
                ? 'Cubierto'
                : `Cubierto · ${line.detail}`;
        case 'optional':
            return 'Opcional';
        case 'missing':
            return line.detail === null ? 'Falta' : `Falta · ${line.detail}`;
    }
}

/** The tools of whoever writes the Programa, or null for who only reads it: the server sends them to the former only. */
export function programTools(props: ProgramPageProps): ProgramTools | null {
    const { catalog, areaOptions, members, manualOptions } = props;

    if (
        catalog === null ||
        areaOptions === null ||
        members === null ||
        manualOptions === null
    ) {
        return null;
    }

    return {
        catalog,
        areas: areaOptions,
        members,
        manualOptions,
        evaluations: props.followUpEvaluations ?? [],
        suggestion: props.followUpSuggestion?.date,
        today: props.today,
        responsible: props.program?.responsible?.name ?? null,
    };
}

/** What the responsible fields say while empty: whose the action falls to (the server inherits it, num. 8.4 f). */
export function inheritedResponsible(name: string | null): string | undefined {
    return name === null ? undefined : `Si lo dejas vacío: ${name}`;
}

/** «2 de 7 temas con acciones · 2 acciones» (board V2-N035-Plan): the themes with an action not cancelled, and how many. */
export function themeCoverage(rows: { meter: { total: number } }[]): {
    covered: number;
    total: number;
    actions: number;
    text: string;
} {
    const covered = rows.filter((row) => row.meter.total > 0).length;

    return {
        covered,
        total: rows.length,
        actions: rows.reduce((sum, row) => sum + row.meter.total, 0),
        text: `${covered} de ${rows.length} ${rows.length === 1 ? 'tema' : 'temas'} con acciones`,
    };
}

const RISK_ORDER = ['very_high', 'high', 'medium', 'low', 'null'];

/** The rows with the highest level first, as the board orders the themes; those RH added, without one, last. */
export function byRisk<R extends { level: { value: string } | null }>(
    rows: R[],
): R[] {
    const rank = (row: R) =>
        row.level === null
            ? RISK_ORDER.length
            : RISK_ORDER.indexOf(row.level.value);

    return rows.toSorted((a, b) => rank(a) - rank(b));
}
