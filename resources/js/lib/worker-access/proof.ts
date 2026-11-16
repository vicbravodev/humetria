import type { Method, PendingVisit, RequestPayload } from '@inertiajs/core';
import type { EntryFailure } from '@/lib/worker-access/entry';
import type { HttpDeps } from '@/lib/worker-access/http';
import { postJson, redirectOf } from '@/lib/worker-access/http';

/** Why a code did not prove it: wrong, expired (or already used), too many tries, not six digits, too many asks, no signal. */
export type CodeFailure =
    | 'wrong'
    | 'expired'
    | 'locked'
    | 'invalid'
    | 'busy'
    | 'failed';

/** Asks for a code by email. Never throws. */
export async function sendProofCode(
    url: string,
    deps: HttpDeps,
): Promise<'sent' | 'busy' | 'failed'> {
    try {
        const response = await postJson(url, {}, deps);

        if (response.ok) {
            return 'sent';
        }

        return response.status === 429 ? 'busy' : 'failed';
    } catch {
        return 'failed';
    }
}

/** The reason of a refused code, as the server says it. */
async function reasonOf(response: Response): Promise<CodeFailure> {
    const json: unknown = await response.json();

    if (typeof json === 'object' && json !== null && 'reason' in json) {
        switch (json.reason) {
            case 'wrong':
            case 'expired':
            case 'locked':
                return json.reason;
        }
    }

    return 'invalid';
}

/** Sends the code the person wrote: where to go once proved, or why not. Never throws. */
export async function checkProofCode(
    url: string,
    code: string,
    deps: HttpDeps,
): Promise<{ redirect: string } | CodeFailure> {
    try {
        const response = await postJson(url, { code }, deps);

        if (response.status === 422) {
            return await reasonOf(response);
        }

        const redirect = response.ok ? await redirectOf(response) : null;

        if (redirect !== null) {
            return { redirect };
        }

        return response.status === 429 ? 'busy' : 'failed';
    } catch {
        return 'failed';
    }
}

const CODE_MESSAGES: Record<CodeFailure, string> = {
    wrong: 'Ese código no es correcto. Revíselo e intente de nuevo.',
    expired:
        'Ese código ya venció o ya se usó. Pida otro con «Enviarme otro código».',
    locked: 'Hubo demasiados intentos con ese código. Pida otro con «Enviarme otro código».',
    invalid: 'Escriba los seis números del código.',
    busy: 'Pidió varios códigos seguidos. Espere unos minutos y vuelva a intentarlo.',
    failed: 'No hay conexión con el servidor. Revise su señal e intente de nuevo.',
};

/** What the screen says when a code did not prove it. */
export function codeMessage(failure: CodeFailure): string {
    return CODE_MESSAGES[failure];
}

/** What the screen says once the code was sent. */
export function codeSentMessage(minutes: number): string {
    return `Le enviamos un código de seis números a su correo. Vence en ${minutes} minutos.`;
}

const KEY_MESSAGES: Record<EntryFailure, string> = {
    cancelled: 'No se confirmó su huella. Vuelva a intentarlo.',
    refused:
        'No reconocimos esa llave. Use la llave que creó para este portal e intente de nuevo.',
    unsupported:
        'Este navegador no puede usar llaves de acceso. Ábralo en el dispositivo donde creó su llave.',
    failed: 'No hay conexión con el servidor. Revise su señal e intente de nuevo.',
};

/** What the screen says when the key did not prove it. */
export function proofKeyMessage(failure: EntryFailure): string {
    return KEY_MESSAGES[failure];
}

/** A form the person sent, to send it again once they prove it is them. */
export type PendingSend = { url: URL; method: Method; data: RequestPayload };

/** The form a visit sends, or null for a page (a page is not sent again: the server takes it to prove and back). */
export function pendingSend(
    visit: Pick<PendingVisit, 'url' | 'method' | 'data'>,
): PendingSend | null {
    return visit.method === 'get'
        ? null
        : { url: visit.url, method: visit.method, data: visit.data };
}
