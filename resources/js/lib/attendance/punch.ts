import type { Reading } from '@/lib/attendance/geolocation';
import { markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import { sendSigned } from '@/lib/attendance/signed-request';
import type { SignDeps } from '@/lib/attendance/signed-request';

/** Who the server verified as signing, and what they may do next. */
export type Signer = { name: string; next: string[] };

/**
 * The person's day as the server tells it: who signed, their marks (type and local time), what may come next and a
 * flag.
 */
export type PunchDay = {
    name: string;
    marks: { type: string; time: string }[];
    next: string[];
    flag: string | null;
    /** How much the mark proves (`strong`, `medium` or `basic`); null when the server named none. */
    evidence: string | null;
};

export type PunchOutcome =
    | { kind: 'done'; day: PunchDay }
    | { kind: 'failed'; reason: string; person: Signer | null };

export type PunchRequest = {
    optionsUrl: string;
    punchUrl: string;
    type: MarkType;
    qr: string | null;
    position: Reading | null;
};

export type PunchDeps = SignDeps;

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const BY_STATUS: Partial<Record<number, string>> = {
    404: 'not_verified',
    429: 'throttled',
};

/** The day in a server's answer; marks or steps it cannot read are left out. Null when it is not a day. */
export function punchDay(json: unknown): PunchDay | null {
    if (
        !isJson(json) ||
        typeof json['name'] !== 'string' ||
        !Array.isArray(json['marks']) ||
        !Array.isArray(json['next'])
    ) {
        return null;
    }

    return {
        name: json['name'],
        marks: json['marks'].flatMap((mark: unknown) =>
            isJson(mark) &&
            typeof mark['type'] === 'string' &&
            typeof mark['time'] === 'string'
                ? [{ type: mark['type'], time: mark['time'] }]
                : [],
        ),
        next: json['next'].filter((step: unknown) => typeof step === 'string'),
        flag: typeof json['flag'] === 'string' ? json['flag'] : null,
        evidence:
            typeof json['evidence'] === 'string' ? json['evidence'] : null,
    };
}

async function failure(response: Response): Promise<PunchOutcome> {
    const known = BY_STATUS[response.status];
    const body: unknown =
        known === undefined && response.status === 422
            ? await response.json()
            : null;
    const reason =
        isJson(body) && typeof body['reason'] === 'string'
            ? body['reason']
            : known;

    return { kind: 'failed', reason: reason ?? 'failed', person: signer(body) };
}

/** Who a rejection names; null when it names nobody readable. */
function signer(body: unknown): Signer | null {
    return isJson(body) &&
        typeof body['name'] === 'string' &&
        Array.isArray(body['next'])
        ? {
              name: body['name'],
              next: body['next'].filter(
                  (step: unknown) => typeof step === 'string',
              ),
          }
        : null;
}

/**
 * Marks: asks the server for a challenge for this browser's session, has the device sign it (the person verifies with
 * their fingerprint, face or PIN) and sends the mark with the center's code and the position, when there are. Never
 * throws.
 */
export async function punch(
    request: PunchRequest,
    deps: PunchDeps,
): Promise<PunchOutcome> {
    try {
        const response = await sendSigned(
            {
                optionsUrl: request.optionsUrl,
                url: request.punchUrl,
                body: {
                    mark_type: request.type,
                    ...(request.qr === null ? {} : { q: request.qr }),
                    ...request.position,
                },
            },
            deps,
        );

        if (typeof response === 'string') {
            return { kind: 'failed', reason: response, person: null };
        }

        if (!response.ok) {
            return failure(response);
        }

        const day = punchDay(await response.json());

        return day === null
            ? { kind: 'failed', reason: 'failed', person: null }
            : { kind: 'done', day };
    } catch {
        return { kind: 'failed', reason: 'failed', person: null };
    }
}

const ASK_RH =
    'Si no puede, pida a Recursos Humanos una corrección de su marca.';

const MESSAGES: Record<string, string> = {
    outside_zone: `Está fuera de la zona de su centro de trabajo. Acérquese e intente de nuevo. ${ASK_RH}`,
    accuracy_too_low: `Su ubicación no es lo bastante precisa. Active la ubicación precisa o salga a un lugar abierto e intente de nuevo. ${ASK_RH}`,
    no_location: `Para marcar se necesita su ubicación en este momento. Permita el acceso a la ubicación e intente de nuevo. ${ASK_RH}`,
    qr_required: `Escanee con la cámara el código QR de la pantalla de su centro de trabajo para marcar. ${ASK_RH}`,
    qr_invalid: `El código QR ya venció o no es de su centro de trabajo. Escanee el código que muestra la pantalla ahora. ${ASK_RH}`,
    day_already_open: 'Ya marcó su entrada.',
    day_not_open: 'Primero marque su entrada.',
    already_on_break: 'Ya marcó su salida a comer.',
    not_on_break: 'Aún no marca su salida a comer.',
    not_verified:
        'No se pudo verificar su dispositivo. Si aún no lo vincula, pida a Recursos Humanos su enlace para vincularlo.',
    cancelled:
        'No se confirmó su huella, su rostro o su PIN. Intente de nuevo.',
    throttled: 'Hubo demasiados intentos. Espere un minuto e intente de nuevo.',
    unsupported:
        'Este navegador no puede usar llaves de acceso. Abra la página en el navegador de su teléfono.',
};

/** What to tell the person when the mark was not taken. */
export function punchMessage(reason: string): string {
    return MESSAGES[reason] ?? 'No se pudo marcar. Intente de nuevo.';
}

/** The flags the person is told about; the others only the panel reads. */
const FLAGGED: Record<string, string> = {
    outside_zone: 'Quedó fuera de zona',
    no_location: 'Quedó sin ubicación',
};

/** What to tell the person once the mark was taken: which, at what time, and whether RH will see it flagged. */
export function doneMessage(day: Pick<PunchDay, 'marks' | 'flag'>): string {
    const last = day.marks.at(-1);

    if (last === undefined) {
        return 'Listo.';
    }

    const done = `Listo: se registró su ${markName(last.type).toLowerCase()} a las ${last.time}.`;

    const flagged = FLAGGED[day.flag ?? ''];

    return flagged === undefined
        ? done
        : `${done} ${flagged} y Recursos Humanos la verá señalada.`;
}
