import type { PunchFeedback } from '@/lib/attendance/punch-state';
import { weekMessage } from '@/lib/attendance/week-messages';
import type { RibbonDay } from '@/lib/attendance/week-ribbon';
import type { WeekDay, WeekView } from '@/lib/attendance/week';

const MONTHS = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

const WEEKDAYS = [
    'Domingo',
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
];

/** A date of the server (Y-m-d) as its parts; read as a calendar date, never in the browser's time zone. */
function parts(date: string): { year: number; month: number; day: number } {
    const [year = 0, month = 1, day = 1] = date.split('-').map(Number);

    return { year, month, day };
}

/** Minutes as hours and minutes: «8 h 30 min», «45 min», «0 h». */
export function duration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (rest === 0) {
        return `${hours} h`;
    }

    return hours === 0 ? `${rest} min` : `${hours} h ${rest} min`;
}

/** «Lunes 2 de noviembre». */
export function dayTitle(date: string): string {
    const { year, month, day } = parts(date);
    const weekday =
        WEEKDAYS[new Date(Date.UTC(year, month - 1, day)).getUTCDay()];

    return `${weekday ?? ''} ${day} de ${MONTHS[month - 1] ?? ''}`;
}

/** «Del 2 al 8 de noviembre de 2026», naming the month and the year of the start only when they differ. */
export function weekTitle(start: string, end: string): string {
    const from = parts(start);
    const to = parts(end);
    const toText = `${to.day} de ${MONTHS[to.month - 1] ?? ''} de ${to.year}`;

    if (from.year !== to.year) {
        return `Del ${from.day} de ${MONTHS[from.month - 1] ?? ''} de ${from.year} al ${toText}`;
    }

    return from.month === to.month
        ? `Del ${from.day} al ${toText}`
        : `Del ${from.day} de ${MONTHS[from.month - 1] ?? ''} al ${toText}`;
}

/** «el 11 de noviembre a las 12:05», from the server's local «Y-m-d H:i». */
function moment(answeredAt: string | null): string {
    const [date = '', time = ''] = (answeredAt ?? '').split(' ');
    const { month, day } = parts(date);

    return `el ${day} de ${MONTHS[month - 1] ?? ''} a las ${time}`;
}

/** Where the week stands, in a word and a sentence. Silence is not conformity: a week nobody answered is «Sin confirmar». */
export function statusText(
    view: Pick<WeekView, 'status' | 'answeredAt' | 'blocked'>,
): {
    label: string;
    detail: string;
} {
    switch (view.status) {
        case 'confirmed':
            return {
                label: 'Confirmada',
                detail: `La confirmó ${moment(view.answeredAt)}.`,
            };
        case 'disputed':
            return {
                label: 'Objetada',
                detail: `La objetó ${moment(view.answeredAt)}. Recursos Humanos revisará su motivo.`,
            };
        case 'open':
            return {
                label: 'En curso',
                detail: 'Podrá confirmarla u objetarla cuando termine el domingo.',
            };
        default:
            return pendingText(view.blocked);
    }
}

const BLOCKED: Record<string, string> = {
    day_open:
        'Su último turno de esta semana sigue abierto: podrá confirmarla cuando marque su salida.',
    no_marks:
        'Esta semana no tiene marcas suyas. Si faltan, use «No estoy de acuerdo» para decirlo.',
    uncovered_days:
        'Le faltan marcas de días que le tocaba trabajar: pida una corrección, o un permiso a Recursos Humanos si no trabajó.',
};

/** A week nobody answered: what to do with it, or why it cannot be confirmed yet. */
function pendingText(blocked: string | null): {
    label: string;
    detail: string;
} {
    return {
        label: 'Sin confirmar',
        detail:
            BLOCKED[blocked ?? ''] ??
            'Revise su semana. Si está bien, confírmela; si no, diga qué no está bien.',
    };
}

const FLAGS: Record<string, string> = {
    day_over_12_hours: 'Un día pasó de 12 horas de trabajo.',
    daily_overtime_over_4: 'Un día tuvo más de 4 horas extra.',
    overtime_days_over_4: 'Tuvo horas extra en más de 4 días de la semana.',
    triple_cap_exceeded: 'Las horas extra al triple pasaron de 4 en la semana.',
};

/** What the person should notice about the week, in plain Spanish: days left with no end, then the limits broken. */
export function weekNotices(
    view: Pick<WeekView, 'days' | 'summary'>,
): string[] {
    const missing = view.days
        .filter((day) => day.missingEnd)
        .map(
            (day) =>
                `El ${dayTitle(day.date).toLowerCase()} quedó sin salida marcada.`,
        );
    const triple =
        view.summary.tripleMinutes > 0
            ? [
                  `Rebasó el tope de horas extra de la semana: ${duration(view.summary.tripleMinutes)} se pagan al triple.`,
              ]
            : [];
    const flags = view.summary.flags.flatMap((flag) => {
        const words = FLAGS[flag];

        return words === undefined ? [] : [words];
    });

    return [...missing, ...triple, ...flags];
}

/** What the last action of «Mi semana» says: why it failed, or what it did. */
export function weekFeedback(
    error: string | null,
    notice: string | null,
): PunchFeedback | null {
    if (error !== null) {
        return { kind: 'error', text: error };
    }

    return notice === null ? null : { kind: 'done', text: notice };
}

/**
 * The hours of the week (hallazgo 21): the total, then ordinary and extra in a line each, and only the kinds of extra
 * there are (doubles, triples, the rest day) detailed under them.
 */
export function weekTotal(view: Pick<WeekView, 'days' | 'summary'>): {
    total: string;
    lines: string[];
    detail: string | null;
} {
    const { summary } = view;
    const worked = view.days.reduce((sum, day) => sum + day.workedMinutes, 0);
    const detail = (
        [
            [summary.doubleMinutes, 'dobles'],
            [summary.tripleMinutes, 'triples'],
            [summary.restDayMinutes, 'en descanso'],
        ] as const
    ).flatMap(([minutes, label]) =>
        minutes > 0 ? [`${duration(minutes)} ${label}`] : [],
    );

    if (worked === 0) {
        return { total: '0 h', lines: ['Sin horas trabajadas'], detail: null };
    }

    return {
        total: duration(worked),
        lines: [
            `${duration(summary.ordinaryMinutes)} ordinarias`,
            `${duration(summary.overtimeMinutes)} extra`,
        ],
        detail: detail.length === 0 ? null : detail.join(' · '),
    };
}

/** The days of the week as the bar draws them: minutes worked and, of them, the overtime, and the days away. */
export function ribbonDays(days: readonly WeekDay[]): RibbonDay[] {
    return days.map((day) => ({
        worked: day.workedMinutes,
        overtime: day.overtimeMinutes,
        ...(absenceLabel(day.absence) !== null && { absent: true }),
    }));
}

/** A day away in a word: «Vacaciones» or «Permiso», never the type of the leave; null when the person was not away. */
export function absenceLabel(absence: WeekDay['absence']): string | null {
    if (absence === 'vacation') {
        return 'Vacaciones';
    }

    return absence === 'leave' ? 'Permiso' : null;
}

/** Why «Confirmar» is turned off: the block and the titles of the days it names. */
export type ConfirmBlock = { reason: string; dates: string[] };

/**
 * What keeps the person from confirming a week the server did offer to read: marks of theirs that wait in the phone (the
 * server cannot know of them, so the app holds the confirmation until they are sent), or the days without cover, each with
 * its title. Null when nothing does.
 */
export function confirmBlock(
    view: Pick<WeekView, 'blocked' | 'uncoveredDays'>,
    queued = 0,
): ConfirmBlock | null {
    if (queued > 0) {
        return { reason: 'queued_marks', dates: [] };
    }

    return view.blocked === 'uncovered_days'
        ? { reason: view.blocked, dates: view.uncoveredDays.map(dayTitle) }
        : null;
}

/** The line under the turned-off «Confirmar mi semana»: «Falta el miércoles 4 de noviembre», or several in one list. */
export function confirmBlockText(block: ConfirmBlock): string {
    if (block.reason === 'queued_marks') {
        return weekMessage('queued_marks');
    }

    const list = new Intl.ListFormat('es', {
        style: 'long',
        type: 'conjunction',
    }).format(block.dates.map((date) => `el ${date.toLowerCase()}`));

    return `${block.dates.length === 1 ? 'Falta' : 'Faltan'} ${list}`;
}

/**
 * The buttons at the foot of «Mi semana»: read it, then confirm it or say what is not right; none while the person writes.
 * «Confirmar» stays, turned off, while days without cover block it: the server sends no address for it then.
 */
export function weekActions(
    view: Pick<
        WeekView,
        'confirmUrl' | 'disputeUrl' | 'blocked' | 'uncoveredDays'
    > | null,
    disputing: boolean,
): ('read' | 'confirm' | 'dispute')[] {
    if (view === null) {
        return ['read'];
    }

    if (disputing) {
        return [];
    }

    return [
        ...(view.confirmUrl === null && confirmBlock(view) === null
            ? []
            : (['confirm'] as const)),
        ...(view.disputeUrl === null ? [] : (['dispute'] as const)),
    ];
}
