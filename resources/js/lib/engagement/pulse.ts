import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { PulseSchedule } from '@/types';

/** What each mode of the pulse looks at besides its fixed questions, in a sentence. */
export function modeHint(mode: string): string {
    switch (mode) {
        case 'follow_up':
            return 'Pregunta de nuevo por las prioridades que dejó el último clima, para ver si mejoran.';
        case 'tour':
            return 'Un par de preguntas de cada tema, para cubrir todos en dos pulsos.';
        case 'manager':
            return 'Dos preguntas sobre el jefe directo y algunas de los temas más importantes.';
        default:
            return '';
    }
}

/** The pill of the home: "Mensual · día 15", or that the schedule is paused. */
export function scheduleBadge(schedule: PulseSchedule): string {
    return schedule.active
        ? `${schedule.frequencyLabel} · día ${schedule.dayOfMonth}`
        : 'En pausa';
}

/** "Sale hoy", "Sale mañana", "Sale en 4 días". */
export function leavesIn(days: number): string {
    if (days === 0) {
        return 'Sale hoy';
    }

    return days === 1
        ? 'Sale mañana'
        : `Sale en ${countOf(days, 'día', 'días')}`;
}

/** "Sale el 15 de diciembre de 2026", or that it is paused. */
export function nextRunTitle(schedule: PulseSchedule): string {
    return schedule.active
        ? `Sale el ${describeDate(schedule.nextRunOn) ?? schedule.nextRunOn}`
        : 'En pausa';
}

/** "Mensual, el día 15 de cada mes.": the rhythm of the schedule, or how a paused one behaves. */
export function cadenceText(schedule: PulseSchedule): string {
    if (!schedule.active) {
        return 'No sale solo hasta que lo reanudes.';
    }

    const every =
        schedule.frequency === 'quarterly' ? 'cada tres meses' : 'de cada mes';

    return `${schedule.frequencyLabel}, el día ${schedule.dayOfMonth} ${every}.`;
}

/** "Recorrido · 10 preguntas": what the next pulse looks at and how long it is. */
export function upcomingText(schedule: PulseSchedule): string {
    return `${schedule.upcoming.modeLabel} · ${countOf(schedule.upcoming.items, 'pregunta', 'preguntas')}`;
}
