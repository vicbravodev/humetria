import { markName } from '@/lib/attendance/next-step';
import { firstName } from '@/lib/attendance/punch-view';
import { weekTitle } from '@/lib/attendance/week-text';
import type { CorrectionDetail, CorrectionMark } from '@/types';

// The one way to take a first name (punch-view), for RH's screens of a correction too.
export { firstName };

/**
 * Deciding a correction with its effect in view (board A3): what approving does, said from what the server found (its
 * CorrectionPreview), and what rejecting does. Nothing here decides: the server refuses what it refuses, this says it first.
 */

export type EffectLine = {
    /** `done` is what happens; `warn` and `info` a thing to know; `block` that the approval will not go through. */
    tone: 'done' | 'warn' | 'info' | 'block';
    text: string;
};

/** «09:00» of the server's local «Y-m-d H:i». */
const clock = (local: string): string => local.split(' ')[1] ?? '';

const lower = (text: string): string =>
    text.charAt(0).toLowerCase() + text.slice(1);

const MONDAY_TO_SUNDAY = 6 * 24 * 60 * 60 * 1000;

/** «del 2 al 8 de noviembre de 2026», from the Monday of the week. */
function weekOf(monday: string): string {
    const [year = 0, month = 1, day = 1] = monday.split('-').map(Number);
    const sunday = new Date(Date.UTC(year, month - 1, day) + MONDAY_TO_SUNDAY);

    return lower(weekTitle(monday, sunday.toISOString().slice(0, 10)));
}

function whatHappens(detail: CorrectionDetail): EffectLine {
    const name = markName(detail.type).toLowerCase();
    const time = clock(detail.requestedAt);

    return {
        tone: 'done',
        text:
            detail.previousAt === null
                ? `Se agrega la marca de ${name} de las ${time} con evidencia Corregida. No hay marca original: es una marca agregada.`
                : `La marca de ${name} de las ${clock(detail.previousAt)} pasa a las ${time}, con evidencia Corregida. La original se conserva y las dos se ven en el reporte.`,
    };
}

function weeksText(weeks: readonly string[]): string {
    if (weeks.length === 0) {
        return 'Su semana aún no está confirmada: no se reabre nada.';
    }

    return weeks.length === 1
        ? `Su semana ${weekOf(weeks[0] ?? '')} estaba confirmada: se reabre y le pedimos confirmarla otra vez.`
        : `Sus semanas ${weeks.map(weekOf).join(' y ')} estaban confirmadas: se reabren y le pedimos confirmarlas otra vez.`;
}

/** What approving the request does, and what to know about it; empty for a request already resolved. */
export function approveLines(
    detail: CorrectionDetail,
    canResolve = true,
): EffectLine[] {
    const { effect } = detail;

    if (effect === null) {
        return [];
    }

    const first = firstName(detail.person.name);
    const lines: EffectLine[] = [
        whatHappens(detail),
        { tone: 'done', text: weeksText(effect.reopensWeeks) },
    ];

    if (canResolve) {
        lines.push({
            tone: 'done',
            text: 'Queda en la bitácora a tu nombre, con tu nota.',
        });
    }

    if (!effect.sequenceOk) {
        lines.push({
            tone: 'block',
            text: `Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta): no se podrá aprobar. Rechaza la solicitud o pide a ${first} otra hora.`,
        });
    } else if (effect.afterApproval === 'missing_end') {
        lines.push({
            tone: 'warn',
            text: `El día seguiría sin marca de salida hasta que ${first} agregue la suya.`,
        });
    }

    if (effect.noSchedule) {
        lines.push({
            tone: 'info',
            text: `${first} no tiene horario: se cuentan 8 h cada día y ningún día de descanso.`,
        });
    }

    if (effect.restDay) {
        lines.push({
            tone: 'info',
            text: `Ese día es de descanso de ${first}: ese tiempo se contaría aparte, como trabajo en descanso.`,
        });
    }

    return lines;
}

/** Whether the server would refuse to approve it. */
export function isBlocked(detail: CorrectionDetail): boolean {
    return detail.effect?.sequenceOk === false;
}

/** What rejecting does. */
export function rejectLines(detail: CorrectionDetail): string[] {
    return [
        'Su registro no cambia: queda como está.',
        `${firstName(detail.person.name)} verá tu nota en «Mi semana» y podrá pedir la corrección de nuevo.`,
    ];
}

export type TimelinePoint = { type: string; percent: number; label: string };

export type Timeline = {
    ticks: string[];
    /** The stretch from the first start to the last end of the day, if it has both. */
    band: { left: number; width: number } | null;
    marks: TimelinePoint[];
    asked: TimelinePoint;
};

const DEFAULT_FROM = 6 * 60;
const DEFAULT_TO = 22 * 60;
const HOUR = 60;
const DAY = 24 * 60;

const minutesOf = (mark: CorrectionMark): number => {
    const [hours = 0, minutes = 0] = mark.time.split(':').map(Number);

    return hours * HOUR + minutes + (mark.nextDay ? DAY : 0);
};

const hhmm = (minutes: number): string => {
    const day = minutes % DAY;

    return `${String(Math.floor(day / HOUR)).padStart(2, '0')}:${String(day % HOUR).padStart(2, '0')}`;
};

/**
 * The day on an axis: 06:00 to 22:00, widened to the whole hour around a mark outside it (a night that ends after midnight
 * runs on past 24:00), with five ticks and each mark as a percentage of it.
 */
export function dayTimeline(
    day: NonNullable<CorrectionDetail['day']>,
): Timeline {
    const all = [...day.marks, day.asked].map(minutesOf);
    const from = Math.min(
        DEFAULT_FROM,
        Math.floor((Math.min(...all) - 30) / HOUR) * HOUR,
    );
    const to = Math.max(
        DEFAULT_TO,
        Math.ceil((Math.max(...all) + 30) / HOUR) * HOUR,
    );
    const percent = (minutes: number): number =>
        ((minutes - from) / (to - from)) * 100;
    const point = (mark: CorrectionMark): TimelinePoint => ({
        type: mark.type,
        percent: percent(minutesOf(mark)),
        label: mark.time,
    });
    const starts = day.marks.filter((mark) => mark.type === 'start');
    const ends = day.marks.filter((mark) => mark.type === 'end');
    const first = starts[0];
    const last = ends.at(-1);

    return {
        ticks: [0, 1, 2, 3, 4].map((step) =>
            hhmm(from + ((to - from) * step) / 4),
        ),
        band:
            first === undefined || last === undefined
                ? null
                : {
                      left: percent(minutesOf(first)),
                      width:
                          percent(minutesOf(last)) - percent(minutesOf(first)),
                  },
        marks: day.marks.map(point),
        asked: point(day.asked),
    };
}
