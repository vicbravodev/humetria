import {
    dueText,
    elapsed,
    isUrgent,
    shortDay,
} from '@/lib/complaints/deadline';
import type { CaseRow } from '@/types';

/** The deadline that counts now for a case of the inbox (board V4-Quejas-Bandeja): its acknowledgement, then its answer. */
export function inboxDue(
    row: CaseRow,
    today: string,
): { text: string; urgent: boolean; share: number } {
    return {
        text: dueText(
            row.acknowledged ? 'Respuesta' : 'Acuse',
            today,
            row.dueOn,
        ),
        urgent: isUrgent(today, row.dueOn),
        share: elapsed(row.receivedOn, row.dueOn, today),
    };
}

const NOUNS: Record<string, string> = {
    unfavorable_practice: 'queja',
    workplace_violence: 'denuncia',
    traumatic_event_notice: 'aviso',
};

/**
 * «Antes de seguir» of the inbox: the first case still waiting for its acknowledgement, which the list puts first, in words
 * for its handler; null when none waits.
 */
export function firstToAcknowledge(
    rows: CaseRow[],
    today: string,
    days: number,
): { id: string; title: string; text: string } | null {
    const row = rows.find(
        (each) => each.status !== 'closed' && !each.acknowledged,
    );

    if (row === undefined) {
        return null;
    }

    const when = dueText('', today, row.dueOn).trim();

    return {
        id: row.id,
        title: `Una persona espera saber que alguien leyó su ${NOUNS[row.type] ?? 'queja'}`,
        text: `${row.reference} llegó el ${shortDay(row.receivedOn)}. Te propusiste confirmar en ${days} ${days === 1 ? 'día' : 'días'} y ${when}. Ya te dejamos el mensaje listo.`,
    };
}
