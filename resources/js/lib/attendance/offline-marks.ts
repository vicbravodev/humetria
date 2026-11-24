import { shortMoment } from '@/lib/attendance/corrections';
import type { WeekMark } from '@/lib/attendance/week';
import type { OfflineMarkDetail } from '@/types';

/** The most marks one request verifies: a page of the queue. */
export const MAX_SELECTION = 25;

const FLAGS: Record<string, string> = {
    wide_interval: 'Más de 4 h sin conexión',
    old_window: 'QR de más de 30 min antes',
    no_position: 'Sin posición',
    outside_zone: 'Fuera de la zona del centro',
    declared_adjusted: 'La hora contada no es la declarada',
};

/** What RH is warned of in a mark, in words; a flag the screen does not know is not shown as its code. */
export function flagText(flag: string): string {
    return FLAGS[flag] ?? 'Otro aviso';
}

const REJECTIONS: Record<string, string> = {
    ticket_expired: 'Su boleto ya había vencido',
    person_inactive: 'La persona ya no estaba activa',
    agreement_not_accepted: 'No había aceptado el acuerdo vigente',
    sequence_conflict: 'La marca no cabía en el orden de su día',
    wrong_center: 'Marcó en un centro que no es el suyo',
    pass_expired: 'Su pase de la app había vencido',
};

/** Why a delivery was not accepted, in words (only the reasons the server keeps). */
export function rejectionText(reason: string | null): string {
    return REJECTIONS[reason ?? ''] ?? 'Sin motivo guardado';
}

/** What the badge of a row says: the mark waits for review, or its delivery was not accepted. */
export function badgeText(view: 'offline' | 'not_accepted'): string {
    return view === 'offline'
        ? 'Sin conexión · por verificar'
        : 'Sin conexión · no aceptada';
}

const SIGNERS: Record<string, string> = {
    passkey: 'Firmada con su llave de acceso',
    device_key: 'Firmada con la llave del dispositivo',
};

/** Which kind of key signed a mark; never which key. */
export function signerText(signer: string): string | null {
    return SIGNERS[signer] ?? null;
}

/** The body of «Verificar seleccionadas»: the marks chosen, with no blanks nor repeats, at most a page of them. */
export function selectionPayload(ids: string[]): { ids: string[] } {
    const chosen = ids.map((id) => id.trim()).filter((id) => id !== '');

    return { ids: [...new Set(chosen)].slice(0, MAX_SELECTION) };
}

/** Every hour of the mark in view, each with its name: nothing is called «intervalo» without saying what it is. */
export function detailHours(
    detail: OfflineMarkDetail,
): { label: string; value: string }[] {
    return [
        { label: 'Hora declarada', value: shortMoment(detail.declaredAt) },
        { label: 'Hora contada', value: shortMoment(detail.countedAt) },
        {
            label: 'Sin conexión desde',
            value: `${shortMoment(detail.ticketIssuedAt)} (boleto)`,
        },
        ...(detail.windowStart === null
            ? []
            : [
                  {
                      label: 'No antes de',
                      value: `${shortMoment(detail.windowStart)} (QR del centro)`,
                  },
              ]),
        { label: 'Llegó', value: shortMoment(detail.receivedAt) },
        ...(detail.phoneClockAt === null
            ? []
            : [
                  {
                      label: 'Reloj del teléfono',
                      value: `${shortMoment(detail.phoneClockAt)} · no es prueba`,
                  },
              ]),
    ];
}

/**
 * Where RH's review of a mark of «Mi semana» stands, for its badge: every mark made without a connection waits for it,
 * with a QR or without; one made online has none.
 */
export function reviewBadge(
    mark: Pick<WeekMark, 'offline' | 'review'>,
): { label: string; variant: 'warning' | 'success' | 'secondary' } | null {
    if (!mark.offline) {
        return null;
    }

    switch (mark.review) {
        case 'verified':
            return { label: 'Verificada', variant: 'success' };
        case 'incorrect':
            return { label: 'RH la marcó incorrecta', variant: 'secondary' };
        default:
            return {
                label: 'Sin conexión · por verificar',
                variant: 'warning',
            };
    }
}

/** The time RH says is right, as the server reads it (the day of the mark and the time given); empty voids the mark. */
export function correctedAt(
    proposedAt: string,
    time: string,
    voided: boolean,
): string {
    const [date = ''] = proposedAt.split(' ');

    return voided || time === '' ? '' : `${date} ${time}`;
}
