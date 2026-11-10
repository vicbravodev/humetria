import { describeDate } from '@/lib/calendar-date';
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

/** "El siguiente pulso sale el 15 de diciembre · Recorrido · 10 preguntas", or that it is paused. */
export function scheduleText(schedule: PulseSchedule): string {
    if (!schedule.active) {
        return 'En pausa: no sale solo hasta que lo reanudes.';
    }

    const day = describeDate(schedule.nextRunOn) ?? schedule.nextRunOn;

    return `El siguiente pulso sale el ${day} · ${schedule.upcoming.modeLabel} · ${schedule.upcoming.items} preguntas`;
}
