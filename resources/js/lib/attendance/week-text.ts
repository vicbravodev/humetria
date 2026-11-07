import type { WeekView } from '@/lib/attendance/week';

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
export function statusText(view: Pick<WeekView, 'status' | 'answeredAt'>): {
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
            return {
                label: 'Sin confirmar',
                detail: 'Revise su semana. Si está bien, confírmela; si no, diga qué no está bien.',
            };
    }
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
