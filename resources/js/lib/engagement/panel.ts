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

/** "1 persona sin correo no recibió su enlace"; nothing when everyone has one. */
export function withoutLinkText(count: number): string | undefined {
    return count === 0
        ? undefined
        : `${countOf(count, 'persona sin correo no recibió', 'personas sin correo no recibieron')} su enlace.`;
}

/** "Faltan 4 por contestar", or that nobody is missing. */
export function pendingText(count: number): string {
    return count === 0
        ? 'Nadie falta por contestar.'
        : `${count === 1 ? 'Falta' : 'Faltan'} ${count} por contestar.`;
}
