/** The five steps of a standing position (board LS-Puesto); the fifth, who holds it, once it is saved. */
export const POSITION_STEPS = [
    { number: 1, title: 'El puesto', next: 'Siguiente: Figura 1' },
    {
        number: 2,
        title: 'Figura 1: dónde va el asiento',
        next: 'Siguiente: condiciones del área',
    },
    {
        number: 3,
        title: 'Condiciones del área',
        next: 'Siguiente: condiciones de las personas',
    },
    { number: 4, title: 'Condiciones de las personas', next: '' },
    { number: 5, title: 'Quién lo ocupa', next: '' },
] as const;

const FIGURE_ONE = [
    'can_work_seated',
    'has_space_for_seat',
    'needs_to_be_nearby',
];
const BASICS = [
    'name',
    'tasks',
    'bipedestation_type',
    'continuous_minutes',
    'max_people_at_once',
];
const AREA = ['area_conditions', 'tools', 'physical_load', 'attention_level'];

function stepOf(field: string): number {
    if (FIGURE_ONE.includes(field)) {
        return 2;
    }

    if (AREA.some((prefix) => field.startsWith(prefix))) {
        return 3;
    }

    return field.startsWith('default_') ? 4 : 1;
}

/** The first step with a field the server rejected, so the form opens where the error is; null without errors. */
export function stepWithError(errors: Record<string, string>): number | null {
    const steps = Object.keys(errors).map(stepOf);

    return steps.length === 0 ? null : Math.min(...steps);
}

/**
 * The result of Figura 1 as the questions are answered (A, B or C), to show it live. The server computes it again when
 * the position is saved (Instruments' SeatingAssessment::placement): A when the person can work seated and there is space;
 * otherwise B when they need to be near their workstation and C when not.
 */
export function figureOne(answers: {
    canWorkSeated: boolean | null;
    hasSpaceForSeat: boolean | null;
    needsToBeNearby: boolean | null;
}): { result: 'A' | 'B' | 'C' | null; thirdNeeded: boolean } {
    const { canWorkSeated, hasSpaceForSeat, needsToBeNearby } = answers;

    if (canWorkSeated === true && hasSpaceForSeat === true) {
        return { result: 'A', thirdNeeded: false };
    }

    if (canWorkSeated === null || (canWorkSeated && hasSpaceForSeat === null)) {
        return { result: null, thirdNeeded: false };
    }

    if (needsToBeNearby === null) {
        return { result: null, thirdNeeded: true };
    }

    return { result: needsToBeNearby ? 'B' : 'C', thirdNeeded: true };
}

/** What the form holds as text, by field name: a radio nobody chose and an empty box are absent or empty. */
export function textValues(data: FormData): Record<string, string> {
    const values: Record<string, string> = {};

    data.forEach((value, key) => {
        if (typeof value === 'string') {
            values[key] = value;
        }
    });

    return values;
}

/**
 * The required fields of a step still empty, so «Siguiente» does not move on over them: the data of the position and the
 * three questions of Figura 1 (the server asks for the same, PositionRequest). The other steps ask nothing here.
 */
export function missingInStep(
    step: number,
    values: Record<string, string>,
): string[] {
    const asked = step === 1 ? BASICS : step === 2 ? FIGURE_ONE : [];

    return asked.filter((field) => (values[field] ?? '').trim() === '');
}

/** The errors the server sent plus «obligatorio» on the fields «Siguiente» found empty; the server's words win. */
export function withMissing(
    sent: Record<string, string>,
    missing: string[],
): Record<string, string> {
    return {
        ...Object.fromEntries(
            missing.map((field) => [field, 'Este dato es obligatorio.']),
        ),
        ...sent,
    };
}
