import { pesos } from '@/lib/overview';
import { toUrl } from '@/lib/utils';
import { show as account } from '@/routes/billing/accounts';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import { messaging } from '@/routes/operations';
import type { OverdueRow } from '@/types';

export type AttentionInput = {
    overdue: { cents: number; accounts: OverdueRow[] };
    trialsEnding: number;
    demosWaiting: { new: number; unattended: number };
    rejectedToday: number;
    dormant: number;
};

export type AttentionTone = 'high' | 'medium' | 'low';

export type AttentionItem = {
    key: string;
    tone: AttentionTone;
    /** What it belongs to, as «Atiende primero» tags it. */
    tag: string;
    title: string;
    detail: string;
    action: string;
    href: string;
};

const ORDER: AttentionTone[] = ['high', 'medium', 'low'];

const count = (n: number, one: string, many: string): string =>
    `${n} ${n === 1 ? one : many}`;

/** What «Hoy» asks the superadmin to look at, the most pressing first; nothing when all is calm. */
export function attentionItems(input: AttentionInput): AttentionItem[] {
    const items: (AttentionItem | null)[] = [
        ...input.overdue.accounts.map((row): AttentionItem => ({
            key: `overdue:${row.id}`,
            tone: 'high',
            tag: 'Cobranza',
            title: `${row.name}: ${pesos(row.cents)} vencido`,
            detail: count(row.days, 'día de atraso', 'días de atraso'),
            action: 'Cobrar',
            href: toUrl(account(row.id)),
        })),
        input.rejectedToday > 0
            ? {
                  key: 'rejected',
                  tone: 'high',
                  tag: 'Mensajería',
                  title: count(
                      input.rejectedToday,
                      'mensaje rechazado hoy',
                      'mensajes rechazados hoy',
                  ),
                  detail: 'Revisa las plantillas y las empresas con más rechazos.',
                  action: 'Ver',
                  href: toUrl(messaging()),
              }
            : null,
        input.trialsEnding > 0
            ? {
                  key: 'trials',
                  tone: 'medium',
                  tag: 'Pruebas',
                  title: count(
                      input.trialsEnding,
                      'prueba termina en 7 días',
                      'pruebas terminan en 7 días',
                  ),
                  detail: 'Buen momento para llamar y proponer un plan.',
                  action: 'Ver',
                  href: toUrl(companies({ query: { status: 'trial_ending' } })),
              }
            : null,
        input.demosWaiting.unattended > 0
            ? {
                  key: 'demos',
                  tone: 'medium',
                  tag: 'Demos',
                  title: count(
                      input.demosWaiting.unattended,
                      'solicitud de demo lleva más de 48 h sin respuesta',
                      'solicitudes de demo llevan más de 48 h sin respuesta',
                  ),
                  detail: `${count(input.demosWaiting.new, 'nueva', 'nuevas')} en total.`,
                  action: 'Responder',
                  href: toUrl(demoRequests({ query: { view: 'board' } })),
              }
            : null,
        input.dormant > 0
            ? {
                  key: 'dormant',
                  tone: 'low',
                  tag: 'Empresas',
                  title: count(
                      input.dormant,
                      'empresa sin entrar en 30 días',
                      'empresas sin entrar en 30 días',
                  ),
                  detail: 'Vale la pena escribirles antes de que se vayan.',
                  action: 'Ver',
                  href: toUrl(companies({ query: { status: 'dormant' } })),
              }
            : null,
    ];

    return items
        .filter((item): item is AttentionItem => item !== null)
        .toSorted((a, b) => ORDER.indexOf(a.tone) - ORDER.indexOf(b.tone));
}
