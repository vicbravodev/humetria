import type { Reading } from '@/lib/attendance/geolocation';
import { markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import type { SignedKey } from '@/lib/attendance/webauthn';
import { assertionPayload, requestOptions } from '@/lib/attendance/webauthn';

/** The person's day as the server tells it: their marks (type and local time), what may come next and a flag. */
export type PunchDay = {
    marks: { type: string; time: string }[];
    next: string[];
    flag: string | null;
};

export type PunchOutcome =
    | { kind: 'done'; day: PunchDay }
    | { kind: 'failed'; reason: string };

export type PunchRequest = {
    optionsUrl: string;
    punchUrl: string;
    type: MarkType;
    qr: string | null;
    position: Reading | null;
};

export type PunchDeps = {
    fetcher: typeof fetch;
    /** `navigator.credentials.get()`, narrowed to a signature or null. */
    getKey: (
        options: PublicKeyCredentialRequestOptions,
    ) => Promise<SignedKey | null>;
    xsrf: string | null;
};

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const BY_STATUS: Partial<Record<number, string>> = {
    404: 'not_verified',
    429: 'throttled',
};

async function post(
    url: string,
    body: unknown,
    { fetcher, xsrf }: PunchDeps,
): Promise<Response> {
    return fetcher(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
        },
        body: JSON.stringify(body),
    });
}

/** The day in a server's answer; marks or steps it cannot read are left out. Null when it is not a day. */
export function punchDay(json: unknown): PunchDay | null {
    if (
        !isJson(json) ||
        !Array.isArray(json['marks']) ||
        !Array.isArray(json['next'])
    ) {
        return null;
    }

    return {
        marks: json['marks'].flatMap((mark: unknown) =>
            isJson(mark) &&
            typeof mark['type'] === 'string' &&
            typeof mark['time'] === 'string'
                ? [{ type: mark['type'], time: mark['time'] }]
                : [],
        ),
        next: json['next'].filter((step: unknown) => typeof step === 'string'),
        flag: typeof json['flag'] === 'string' ? json['flag'] : null,
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

    return { kind: 'failed', reason: reason ?? 'failed' };
}

/** The signature of the device, or why there is none. */
async function sign(
    options: PublicKeyCredentialRequestOptions,
    deps: PunchDeps,
): Promise<SignedKey | string> {
    try {
        return (await deps.getKey(options)) ?? 'failed';
    } catch (error) {
        return error instanceof DOMException && error.name === 'NotAllowedError'
            ? 'cancelled'
            : 'failed';
    }
}

async function signed(
    request: PunchRequest,
    deps: PunchDeps,
): Promise<SignedKey | PunchOutcome> {
    const response = await post(request.optionsUrl, {}, deps);

    if (!response.ok) {
        return failure(response);
    }

    const options = requestOptions(await response.json());
    const key = options === null ? 'failed' : await sign(options, deps);

    return typeof key === 'string' ? { kind: 'failed', reason: key } : key;
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
        const key = await signed(request, deps);

        if ('kind' in key) {
            return key;
        }

        const response = await post(
            request.punchUrl,
            {
                mark_type: request.type,
                credential: assertionPayload(key),
                ...(request.qr === null ? {} : { q: request.qr }),
                ...request.position,
            },
            deps,
        );

        if (!response.ok) {
            return failure(response);
        }

        const day = punchDay(await response.json());

        return day === null
            ? { kind: 'failed', reason: 'failed' }
            : { kind: 'done', day };
    } catch {
        return { kind: 'failed', reason: 'failed' };
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

/** What to tell the person once the mark was taken: which, at what time, and whether RH will see it flagged. */
export function doneMessage(day: PunchDay): string {
    const last = day.marks.at(-1);

    if (last === undefined) {
        return 'Listo.';
    }

    const done = `Listo: se registró su ${markName(last.type).toLowerCase()} a las ${last.time}.`;

    return day.flag === 'outside_zone'
        ? `${done} Quedó fuera de zona y Recursos Humanos la verá señalada.`
        : done;
}
