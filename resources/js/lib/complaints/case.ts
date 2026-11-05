import type { CaseDetail } from '@/types';

/** The line under a case's reference: where, how it was presented and the day it came — never the hour (decisión D2). */
export function caseLead(detail: CaseDetail, receivedDay: string): string {
    return [
        detail.center ?? 'Sin centro indicado',
        detail.identity === 'anonymous' ? 'anónima' : 'confidencial',
        `recibida el ${receivedDay}`,
    ].join(' · ');
}

const NOUNS: Record<string, string> = {
    unfavorable_practice: 'la queja',
    workplace_violence: 'la denuncia',
    traumatic_event_notice: 'el aviso',
};

/** What the system told the person when the case came: it opens the conversation. */
export function receivedLine(detail: CaseDetail): string {
    return `Se recibió ${NOUNS[detail.type] ?? 'la queja'} y se dio a la persona la referencia ${detail.reference}.`;
}
