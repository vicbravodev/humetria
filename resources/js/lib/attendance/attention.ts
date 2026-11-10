import { overviewQuery } from '@/lib/attendance/overview';
import type { OverviewFilters } from '@/lib/attendance/overview';
import { index, center } from '@/routes/tenant/attendance';
import { index as corrections } from '@/routes/tenant/attendance/corrections';
import type { AttendanceOverviewProps, OverviewView } from '@/types';

/** What the overview knows that may ask RH for something. */
export type AttentionInput = {
    /** The Monday of the week shown. */
    week: string;
    over: boolean;
    /** The center the overview is narrowed to; empty for every center. */
    center: string;
    counts: AttendanceOverviewProps['counts'];
    pendingCorrections: number;
    centersWithoutZones: number;
    findings: AttendanceOverviewProps['setup']['findings'];
};

/** A thing that asks for RH: how loud it is, what it says and the way to settle it. */
export type AttentionItem = {
    key: string;
    /** `alert` waits for a decision of RH; `warn` is a risk or something missing. */
    tone: 'alert' | 'warn';
    title: string;
    text: string;
    action: string;
    /** A page, or an anchor of this one (`#…`). */
    href: string;
};

const plural = (count: number, one: string, many: string): string =>
    `${count} ${count === 1 ? one : many}`;

/**
 * What needs RH's attention, in the order it blocks most (board A1 «Necesita tu atención»): first what waits for a
 * decision of RH, then the weeks at risk or unanswered, then the centers that cannot take a mark well. Empty when nothing.
 */
export function attentionItems(
    tenantSlug: string,
    input: AttentionInput,
): AttentionItem[] {
    const { counts } = input;
    const tab = (status: OverviewView): string =>
        index(tenantSlug, {
            query: overviewQuery({
                q: '',
                status,
                week: input.week,
                center: input.center,
            } satisfies OverviewFilters),
        }).url;
    const items: (AttentionItem | null)[] = [
        input.pendingCorrections > 0
            ? {
                  key: 'corrections',
                  tone: 'alert',
                  title: `${plural(input.pendingCorrections, 'corrección', 'correcciones')} por resolver`,
                  text: 'Esperan una decisión tuya: aprueba o rechaza cada una.',
                  action: 'Revisar correcciones',
                  href: corrections(tenantSlug).url,
              }
            : null,
        counts.disputed > 0
            ? {
                  key: 'disputed',
                  tone: 'alert',
                  title:
                      counts.disputed === 1
                          ? '1 persona objetó su semana'
                          : `${counts.disputed} personas objetaron su semana`,
                  text: 'Cada objeción dice qué no está bien: léela y corrige lo que proceda.',
                  action: 'Ver objeciones',
                  href: tab('disputed'),
              }
            : null,
        counts.at_risk > 0
            ? {
                  key: 'at_risk',
                  tone: 'warn',
                  title: `${plural(counts.at_risk, 'persona', 'personas')} cerca o por encima de un tope legal`,
                  text: 'Más de 4 h extra en un día, extra en más de 4 días o más de 12 h al día.',
                  action: 'Ver quiénes',
                  href: tab('at_risk'),
              }
            : null,
        input.over && counts.pending > 0
            ? {
                  key: 'pending',
                  tone: 'warn',
                  title:
                      counts.pending === 1
                          ? '1 persona no ha confirmado su semana'
                          : `${counts.pending} personas no han confirmado su semana`,
                  text: 'El silencio no cuenta como conformidad: la semana queda sin confirmar.',
                  action: 'Ver quiénes',
                  href: tab('pending'),
              }
            : null,
    ];

    return [
        ...items.filter((item) => item !== null),
        ...input.findings.map((finding): AttentionItem => ({
            key: `zone-${finding.center.id}`,
            tone: 'warn',
            title: `La zona ${finding.zone} de ${finding.center.name} es menor que la precisión de la política`,
            text: `Su radio es de ${finding.radius} m y aceptas lecturas de hasta ${finding.tolerance} m de imprecisión: una marca con señal débil podría parecer dentro sin estarlo.`,
            action: 'Ajustar la zona',
            href: center({
                tenant: tenantSlug,
                workCenter: finding.center.id,
            }).url,
        })),
        ...(input.centersWithoutZones > 0
            ? [
                  {
                      key: 'zones',
                      tone: 'warn',
                      title: `${input.centersWithoutZones} ${input.centersWithoutZones === 1 ? 'centro' : 'centros'} sin zonas`,
                      text: 'Ahí nadie puede marcar con su ubicación.',
                      action: 'Agregar zonas',
                      href: '#centros',
                  } satisfies AttentionItem,
              ]
            : []),
    ];
}
