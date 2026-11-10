import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { ClimateSurveyCard } from '@/types';

/** "Cierra el 20 de noviembre de 2026 · quedan 3 días"; nothing for a draft, which has no last day. */
export function deadlineText(card: ClimateSurveyCard): string | undefined {
    if (card.closesOn === null) {
        return undefined;
    }

    const day = describeDate(card.closesOn) ?? card.closesOn;

    if (card.status === 'closed') {
        return `Cerró el ${day}`;
    }

    if (card.daysLeft === null) {
        return `Cierra el ${day}`;
    }

    return card.daysLeft === 1
        ? `Cierra el ${day} · último día`
        : `Cierra el ${day} · quedan ${countOf(card.daysLeft, 'día', 'días')}`;
}

/** "18 de 60 contestaron" for a survey that was sent; what the draft still lacks otherwise. */
export function answeredText(card: ClimateSurveyCard): string {
    return card.invited === null || card.answered === null
        ? 'Todavía no se lanza: nadie ha recibido nada.'
        : `${card.answered} de ${card.invited} contestaron`;
}
