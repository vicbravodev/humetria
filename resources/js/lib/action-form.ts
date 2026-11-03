import type {
    ActionCatalog,
    ActionStatusValue,
    AreaOption,
    ProgramAction,
} from '@/types';

/** What Results shows for an area and a row: how many of its questionnaires are in Medio or more. */
export function atRiskText(count: number): string {
    if (count === 0) {
        return 'Ninguno en riesgo medio o más';
    }

    return `${count} ${count === 1 ? 'cuestionario' : 'cuestionarios'} en riesgo medio o más`;
}

/** The areas to choose for a row, each with its count of that row. */
export function areaChoices(
    areas: AreaOption[],
    itemId: string,
): { id: string; name: string; hint: string }[] {
    return areas.map((area) => ({
        id: area.id,
        name: area.name,
        hint: atRiskText(area.atRisk[itemId] ?? 0),
    }));
}

/** The level a kind of action usually belongs to; null for the kind that has none of its own. */
export function levelOfKind(
    catalog: ActionCatalog,
    kind: string,
): number | null {
    return (
        catalog.kinds.find((candidate) => candidate.value === kind)?.level ??
        null
    );
}

/** Whether the level needs the professional who gives it: only the third one, clinical care (num. 8.5). */
export function needsProfessional(level: number): boolean {
    return level === 3;
}

/** What the kind, level and subjects of the action form start with. */
function kindDefaults(catalog: ActionCatalog, action: ProgramAction | null) {
    const kind = action?.kind.value ?? catalog.kinds[0]?.value ?? 'other';

    return {
        kind,
        level: action?.level.value ?? levelOfKind(catalog, kind) ?? 1,
    };
}

/** What the professional and the subjects of the action form start with. */
function whoDefaults(action: ProgramAction | null) {
    return {
        professional: action?.professional?.value ?? 'psychologist',
        subjects: action?.subjects.value ?? 'center',
    };
}

/** What the written fields and the dates of the action form start with. */
function textDefaults(action: ProgramAction | null) {
    return {
        title: action?.title ?? '',
        description: action?.description ?? '',
        startsOn: action?.startsOn ?? undefined,
        dueOn: action?.dueOn,
    };
}

/** What the responsible and the areas of the action form start with. */
function peopleDefaults(action: ProgramAction | null) {
    return {
        userId: action?.responsible.userId ?? null,
        name: action?.responsible.name ?? '',
        areaIds: action?.areas.map((area) => area.id) ?? [],
    };
}

/** What the action form starts with: the action being edited, or the first kind of the catalog for a new one. */
export function actionDefaults(
    catalog: ActionCatalog,
    action: ProgramAction | null,
) {
    return {
        ...kindDefaults(catalog, action),
        ...whoDefaults(action),
        ...textDefaults(action),
        ...peopleDefaults(action),
    };
}

/** The level after choosing a kind: the one the kind belongs to; for the kind without one, the one it had, unless it was the clinical third. */
export function levelAfterKind(
    catalog: ActionCatalog,
    kind: string,
    current: number,
): number {
    return (
        levelOfKind(catalog, kind) ?? (needsProfessional(current) ? 1 : current)
    );
}

const STEP_LABELS: Record<ActionStatusValue, string> = {
    planned: 'Planeada',
    in_progress: 'En curso',
    done: 'Hecha',
    cancelled: 'Cancelada',
};

/** The states an action can move to from where it is: forward, a note while running, or reopened from done. */
export function nextStatuses(
    status: ActionStatusValue,
): { value: ActionStatusValue; label: string }[] {
    const targets: Record<ActionStatusValue, ActionStatusValue[]> = {
        planned: ['in_progress', 'done'],
        in_progress: ['in_progress', 'done'],
        done: ['in_progress'],
        cancelled: [],
    };

    return targets[status].map((value) => ({
        value,
        label:
            status === 'done' && value === 'in_progress'
                ? 'Reabrir (en curso)'
                : STEP_LABELS[value],
    }));
}

/** Whether an action takes steps or a cancellation: a cancelled one is closed for good (criterio C-35). */
export function isOpen(status: ActionStatusValue): boolean {
    return status !== 'cancelled';
}

const LIFE: ActionStatusValue[] = [
    'planned',
    'in_progress',
    'done',
    'cancelled',
];

/** The states an action can be filtered by, in the order of its life. */
export function statusChoices(): { value: ActionStatusValue; label: string }[] {
    return LIFE.map((value) => ({ value, label: STEP_LABELS[value] }));
}
