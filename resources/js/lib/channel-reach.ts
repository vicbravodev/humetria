import type { ChannelAnswers, ChannelTally, ReachChannel } from '@/types';

/** The channel as a person reads it. */
export function channelLabel(channel: ReachChannel): string {
    return channel === 'mail' ? 'Correo' : 'WhatsApp';
}

/**
 * What a channel's gauge says, under the minimum of rule 10: nothing of the numbers below it, and "menos de 5" instead of the
 * undelivered when they are too few to show without pointing at a person.
 */
export function tallyText(tally: ChannelTally, minGroup: number): string {
    if (tally.total === null) {
        return `Menos de ${minGroup} mensajes`;
    }

    if (tally.delivered === null || tally.undelivered === null) {
        return `${tally.total} mensajes · menos de ${minGroup} sin entregar`;
    }

    return `${tally.total} mensajes · ${tally.delivered} entregados · ${tally.undelivered} sin entregar`;
}

/** "12 contestaron · 8 no": a row of the table of answers by channel. */
export function answersText(row: ChannelAnswers): string {
    return `${row.answered} contestaron · ${row.unanswered} no`;
}

/** Why the table of answers by channel is not there. */
export function hiddenAnswersText(minGroup: number): string {
    return `Se muestra cuando cada grupo tiene al menos ${minGroup} personas`;
}
