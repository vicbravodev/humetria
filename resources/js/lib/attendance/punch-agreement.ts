import { sendSigned } from '@/lib/attendance/signed-request';
import type { SignDeps } from '@/lib/attendance/signed-request';

/** The company's current agreement on the electronic record, as the server gives it to read (criterio 5.1). */
export type Agreement = { version: number; body: string };

export type AcceptRequest = {
    optionsUrl: string;
    url: string;
    version: number;
};

export type AcceptOutcome =
    | { kind: 'accepted' }
    | { kind: 'outdated'; agreement: Agreement }
    | { kind: 'failed'; reason: string };

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The agreement an answer of the server brings to read; null when it brings none it can read. */
export function agreementOf(body: unknown): Agreement | null {
    const agreement = isJson(body) ? body['agreement'] : null;

    return isJson(agreement) &&
        typeof agreement['version'] === 'number' &&
        typeof agreement['body'] === 'string'
        ? { version: agreement['version'], body: agreement['body'] }
        : null;
}

const BY_STATUS: Partial<Record<number, string>> = {
    404: 'not_verified',
    429: 'throttled',
};

async function outcomeOf(response: Response): Promise<AcceptOutcome> {
    if (response.ok) {
        return { kind: 'accepted' };
    }

    const agreement =
        response.status === 409 ? agreementOf(await response.json()) : null;

    return agreement === null
        ? { kind: 'failed', reason: BY_STATUS[response.status] ?? 'failed' }
        : { kind: 'outdated', agreement };
}

/**
 * Accepts the version of the agreement the person read: a challenge of its own, their fingerprint, face or PIN, and the
 * version. The new text when it changed meanwhile. Never throws.
 */
export async function acceptAgreement(
    { optionsUrl, url, version }: AcceptRequest,
    deps: SignDeps,
): Promise<AcceptOutcome> {
    try {
        const response = await sendSigned(
            { optionsUrl, url, body: { agreement_version: version } },
            deps,
        );

        return typeof response === 'string'
            ? { kind: 'failed', reason: response }
            : await outcomeOf(response);
    } catch {
        return { kind: 'failed', reason: 'failed' };
    }
}

const MESSAGES: Record<string, string> = {
    outdated: 'El acuerdo cambió. Lea la versión nueva y acéptela para marcar.',
    cancelled:
        'No se confirmó su huella, su rostro o su PIN. Intente de nuevo.',
    throttled: 'Hubo demasiados intentos. Espere un minuto e intente de nuevo.',
    not_verified:
        'No se pudo verificar su dispositivo. Pida a Recursos Humanos su enlace para vincularlo.',
    unsupported:
        'Este navegador no puede usar llaves de acceso. Abra la página en el navegador de su teléfono.',
};

/** What to tell the person when their acceptance did not go through. */
export function agreementMessage(reason: string): string {
    return (
        MESSAGES[reason] ??
        'No se pudo enviar su aceptación. Revise su conexión e intente de nuevo.'
    );
}

/** The agreement's sections: one per paragraph, its title up to the first period. */
export function agreementSections(
    body: string,
): { title: string; text: string }[] {
    return body.split('\n\n').map((paragraph) => {
        const end = paragraph.indexOf('. ');

        return end === -1
            ? { title: '', text: paragraph }
            : {
                  title: paragraph.slice(0, end),
                  text: paragraph.slice(end + 2),
              };
    });
}
