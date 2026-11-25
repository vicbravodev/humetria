import { countOf } from '@/lib/plural';

/*
 * What the person reads about the marks they made without a connection, in «usted»: the one place of those texts. The
 * states and where each is seen are in docs/superpowers/specs/2026-11-20-worker-pwa-offline-design.md («Estados de una marca»).
 */

export const SAVED_TITLE = 'Guardada sin conexión';

export const OPEN_ONLINE = 'Abra Humetría con internet';

export const OPEN_ONLINE_SEND =
    'Abra Humetría con internet para enviar su marca.';

export const LOSS_WARNING =
    'Si borra los datos de la app antes de que se envíe, esta marca se pierde.';

export const FEW_TICKETS =
    'Pocos boletos: abra Humetría con internet para recibir más.';

const GENERIC_NOTE = 'Recursos Humanos la revisará';

/** Why the server did not accept a mark, said so the person understands what to do next. */
const NOTES: Record<string, string> = {
    ticket_expired: 'la app llevaba más de 35 días sin internet',
    person_inactive: 'su registro no está activo',
    agreement_not_accepted: 'faltaba aceptar el acuerdo de registro',
    sequence_conflict:
        'no sigue el orden de sus otras marcas (puede ser una marca repetida)',
    wrong_center: 'el centro no es el suyo',
    pass_expired: 'el permiso de este teléfono para marcar ya había vencido',
    ticket_invalid:
        'la app no pudo comprobar su permiso para marcar sin internet',
    signature_invalid:
        'no se pudo comprobar su llave, o ya no era válida a esa hora',
    ticket_reused: 'ese permiso para marcar ya se había usado en otra marca',
    malformed: 'la marca se guardó dañada en el teléfono',
};

/** The reasons that leave no row at the server: it does not have the mark, so dropping it loses it for good. */
const NOT_AT_SERVER = [
    'ticket_invalid',
    'signature_invalid',
    'ticket_reused',
    'malformed',
];

/** After tapping: the hour the person said, and that RH does not have it yet. */
export function savedText(declared: string): string {
    return `Usted indicó ${declared}. Aún no la recibe Recursos Humanos.`;
}

export function receivedText(): string {
    return 'Recibida. Cuenta en sus horas. Recursos Humanos la revisará.';
}

export function alreadyRecordedText(): string {
    return 'Ya estaba registrada.';
}

export function queueCount(count: number): string {
    return `${countOf(count, 'marca', 'marcas')} sin enviar`;
}

export function refusalNote(reason: string | null): string {
    return (reason === null ? undefined : NOTES[reason]) ?? GENERIC_NOTE;
}

export function refusedText(reason: string | null): string {
    return `No aceptada: ${refusalNote(reason)}. Puede pedir una corrección.`;
}

/** Whether RH has no record of a mark the server refused: then «Entendido» asks first, since it would be lost. */
export function unsavedOnServer(reason: string | null): boolean {
    return reason !== null && NOT_AT_SERVER.includes(reason);
}
