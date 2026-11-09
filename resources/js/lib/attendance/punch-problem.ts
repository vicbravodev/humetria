import type { LocationError } from '@/lib/attendance/geolocation';
import type { PunchFeedback } from '@/lib/attendance/punch-state';

/**
 * The errors of Marcar (board P3): what happened and what to do, in words that blame nobody. The server gives only a stable
 * reason; the screen says it here. An order of marks that does not fit is not one of these: the steps are still there.
 */
export type ProblemKind =
    | 'zone'
    | 'location'
    | 'qr'
    | 'device'
    | 'verify'
    | 'wait'
    | 'browser'
    | 'generic';

export type ProblemAction = {
    kind: 'retry' | 'correction' | 'howto' | 'copy-hr' | 'dismiss';
    label: string;
};

export type Problem = {
    kind: ProblemKind;
    tone: 'warning' | 'blocked';
    title: string;
    text: string;
    actions: ProblemAction[];
};

const SEQUENCE = new Set([
    'day_already_open',
    'day_not_open',
    'already_on_break',
    'not_on_break',
]);

/** A mark that came out of order: whoever tapped may not be who the screen showed, so every step is offered again. */
export function isSequence(reason: string): boolean {
    return SEQUENCE.has(reason);
}

const RETRY: ProblemAction = { kind: 'retry', label: 'Intentar de nuevo' };
const CORRECTION: ProblemAction = {
    kind: 'correction',
    label: 'Pedir una corrección',
};
const DISMISS: ProblemAction = { kind: 'dismiss', label: 'Entendido' };
const ASK_RH =
    'Si no puede, pida a Recursos Humanos una corrección de su marca.';

function locationProblem(location: LocationError | null): Problem {
    if (location === 'timeout' || location === 'unavailable') {
        return {
            kind: 'location',
            tone: 'warning',
            title: 'No encontramos su ubicación',
            text: `Salga a un lugar abierto o active la ubicación precisa e inténtelo otra vez. ${ASK_RH}`,
            actions: [RETRY, CORRECTION],
        };
    }

    return {
        kind: 'location',
        tone: 'warning',
        title: 'No tenemos su ubicación',
        text: `Permita la ubicación para este sitio. Solo la usamos al marcar. ${ASK_RH}`,
        actions: [
            { kind: 'retry', label: 'Permitir ubicación' },
            { kind: 'howto', label: 'Cómo activarla' },
            CORRECTION,
        ],
    };
}

const PROBLEMS: Record<string, Problem> = {
    outside_zone: {
        kind: 'zone',
        tone: 'blocked',
        title: 'Está fuera de la zona',
        text: `Acérquese a su centro de trabajo e inténtelo otra vez. ${ASK_RH}`,
        actions: [RETRY, CORRECTION],
    },
    accuracy_too_low: {
        kind: 'zone',
        tone: 'warning',
        title: 'Su ubicación no es lo bastante precisa',
        text: `Active la ubicación precisa o salga a un lugar abierto e inténtelo otra vez. ${ASK_RH}`,
        actions: [RETRY, CORRECTION],
    },
    qr_required: {
        kind: 'qr',
        tone: 'warning',
        title: 'Falta escanear el código',
        text: `Escanee con la cámara de su teléfono el código de la pantalla de su centro de trabajo y marque desde ahí. ${ASK_RH}`,
        actions: [DISMISS, CORRECTION],
    },
    qr_invalid: {
        kind: 'qr',
        tone: 'warning',
        title: 'Ese código ya venció',
        text: `Cada código dura 30 segundos y solo sirve en su centro. Escanee el que se ve ahora en la pantalla. ${ASK_RH}`,
        actions: [DISMISS, CORRECTION],
    },
    not_verified: {
        kind: 'device',
        tone: 'warning',
        title: 'Este teléfono no está vinculado',
        text: 'Para marcar necesita vincularlo primero. Pida el enlace a Recursos Humanos: le llegará a su correo y sirve una sola vez.',
        actions: [
            { kind: 'copy-hr', label: 'Copiar mensaje para RH' },
            DISMISS,
        ],
    },
    cancelled: {
        kind: 'verify',
        tone: 'warning',
        title: 'No se confirmó que es usted',
        text: 'Su teléfono no recibió su huella, su rostro o su PIN. Inténtelo de nuevo.',
        actions: [RETRY],
    },
    throttled: {
        kind: 'wait',
        tone: 'warning',
        title: 'Hubo demasiados intentos',
        text: 'Espere un minuto e inténtelo de nuevo.',
        actions: [DISMISS],
    },
    unsupported: {
        kind: 'browser',
        tone: 'warning',
        title: 'Este navegador no puede marcar',
        text: 'No puede usar llaves de acceso. Abra la página en el navegador de su teléfono.',
        actions: [DISMISS],
    },
};

const GENERIC: Problem = {
    kind: 'generic',
    tone: 'blocked',
    title: 'No se pudo marcar',
    text: 'Revise su conexión e inténtelo de nuevo.',
    actions: [RETRY],
};

/** The problem a refused mark is, said for the screen; null for one that only came out of order. */
export function punchProblem(
    reason: string,
    location: LocationError | null = null,
): Problem | null {
    if (isSequence(reason)) {
        return null;
    }

    return reason === 'no_location'
        ? locationProblem(location)
        : (PROBLEMS[reason] ?? GENERIC);
}

/** The problem the last answer of a mark is, if it is one: an error with its reason that is not just an order that did not fit. */
export function feedbackProblem(
    feedback: PunchFeedback | null,
    location: LocationError | null,
): Problem | null {
    return feedback?.kind === 'error' && feedback.reason !== undefined
        ? punchProblem(feedback.reason, location)
        : null;
}

/** What the person sends RH when their phone is not linked: asks for the link, with their company's name. */
export function hrLinkMessage(company: string): string {
    return `Hola, necesito el enlace para vincular mi teléfono y marcar mi jornada en Humetría (${company}). Gracias.`;
}

/** How to allow the location in the phone, when the browser no longer asks. */
export const LOCATION_HOWTO =
    'Abra los ajustes de su teléfono, entre a Privacidad y ubicación, y permita el acceso al navegador que usa. Luego vuelva aquí y toque «Permitir ubicación».';
