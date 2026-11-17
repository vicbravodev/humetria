import type { HttpDeps } from '@/lib/worker-access/http';
import {
    answerFailure,
    postJson,
    promptFailure,
    redirectOf,
    SERVER_TROUBLE,
} from '@/lib/worker-access/http';
import type { SignedKey } from '@/lib/webauthn';
import { assertionPayload, requestOptions } from '@/lib/webauthn';

/** Why entering with the key did not open the session. */
export type EntryFailure =
    | 'cancelled'
    | 'refused'
    | 'failed'
    | 'server'
    | 'unsupported';

/** The session opened (and where to go), or why not. */
export type EntryOutcome = { redirect: string } | EntryFailure;

export type EntryDeps = HttpDeps & {
    /** `navigator.credentials.get()`, narrowed to a signature or null. */
    getKey: (
        options: PublicKeyCredentialRequestOptions,
    ) => Promise<SignedKey | null>;
};

async function sign(
    options: PublicKeyCredentialRequestOptions,
    deps: EntryDeps,
): Promise<SignedKey | EntryFailure> {
    try {
        return (await deps.getKey(options)) ?? 'failed';
    } catch (error) {
        return promptFailure(error);
    }
}

/**
 * Enters with the key: asks for a challenge for this browser's session, has the device sign it (the person verifies with
 * their fingerprint, face or PIN) and sends the signature. Never throws.
 */
export async function enterWithKey(
    urls: { optionsUrl: string; enterUrl: string },
    deps: EntryDeps,
): Promise<EntryOutcome> {
    try {
        const response = await postJson(urls.optionsUrl, {}, deps);

        if (!response.ok) {
            return answerFailure(response.status);
        }

        const options = requestOptions(await response.json());

        if (options === null) {
            return 'failed';
        }

        const key = await sign(options, deps);

        if (typeof key === 'string') {
            return key;
        }

        const entered = await postJson(
            urls.enterUrl,
            { credential: assertionPayload(key) },
            deps,
        );

        if (entered.status === 422) {
            return 'refused';
        }

        if (!entered.ok) {
            return answerFailure(entered.status);
        }

        const redirect = await redirectOf(entered);

        return redirect === null ? 'failed' : { redirect };
    } catch {
        return 'failed';
    }
}

/** What to tell the person when entering did not work: short, and what to do next. */
export function entryMessage(failure: EntryFailure): string {
    switch (failure) {
        case 'cancelled':
            return 'No se confirmó su huella. Vuelva a intentarlo.';
        case 'refused':
            return 'No reconocimos esa llave. Intente de nuevo o pida un enlace por correo.';
        case 'unsupported':
            return 'Este navegador no puede usar llaves de acceso. Pida un enlace por correo.';
        case 'server':
            return SERVER_TROUBLE;
        default:
            return 'No hay conexión con el servidor. Revise su señal e intente de nuevo.';
    }
}

/** How asking for a link by email went: the same `sent` whether the email is anyone's or not. */
export type LinkRequestOutcome =
    | 'sent'
    | 'invalid'
    | 'busy'
    | 'failed'
    | 'server';

/** Asks for a one-time link to `email`. Never throws. */
export async function requestLink(
    url: string,
    email: string,
    deps: HttpDeps,
): Promise<LinkRequestOutcome> {
    try {
        const response = await postJson(url, { email }, deps);

        if (response.ok) {
            return 'sent';
        }

        return response.status === 422
            ? 'invalid'
            : response.status === 429
              ? 'busy'
              : answerFailure(response.status);
    } catch {
        return 'failed';
    }
}

const LINK_MESSAGES: Record<LinkRequestOutcome, string> = {
    sent: 'Si ese correo es de una persona de la empresa, le llegará un enlace en unos minutos. Sirve una vez y vence en 30 minutos.',
    invalid: 'Escriba un correo válido.',
    busy: 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.',
    server: SERVER_TROUBLE,
    failed: 'No hay conexión con el servidor. Revise su señal e intente de nuevo.',
};

/** What the screen says after asking for a link. */
export function linkRequestMessage(outcome: LinkRequestOutcome): string {
    return LINK_MESSAGES[outcome];
}
