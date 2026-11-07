import type { CreatedKey } from '@/lib/attendance/webauthn';
import {
    creationOptions,
    registrationPayload,
} from '@/lib/attendance/webauthn';

/** How linking the device ended. */
export type EnrollOutcome =
    | 'done'
    | 'cancelled'
    | 'failed'
    | 'outdated'
    | 'gone'
    | 'unsupported';

export type EnrollRequest = {
    optionsUrl: string;
    registerUrl: string;
    /** The version of the agreement the person read and accepted. */
    agreement: number;
    label: string;
};

export type EnrollDeps = {
    fetcher: typeof fetch;
    /** `navigator.credentials.create()`, narrowed to a new key or null. */
    createKey: (
        options: PublicKeyCredentialCreationOptions,
    ) => Promise<CreatedKey | null>;
    xsrf: string | null;
};

const BY_STATUS: Partial<Record<number, EnrollOutcome>> = {
    404: 'gone',
    409: 'outdated',
};

async function post(
    url: string,
    body: unknown,
    { fetcher, xsrf }: EnrollDeps,
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

/** The options of a new key, or how asking for them failed. */
async function askOptions(
    request: EnrollRequest,
    deps: EnrollDeps,
): Promise<PublicKeyCredentialCreationOptions | EnrollOutcome> {
    const response = await post(request.optionsUrl, {}, deps);

    if (!response.ok) {
        return BY_STATUS[response.status] ?? 'failed';
    }

    return creationOptions(await response.json()) ?? 'failed';
}

/** The new key, or `cancelled` when the person closed the prompt or did not verify. */
async function createKey(
    options: PublicKeyCredentialCreationOptions,
    deps: EnrollDeps,
): Promise<CreatedKey | EnrollOutcome> {
    try {
        return (await deps.createKey(options)) ?? 'failed';
    } catch (error) {
        return error instanceof DOMException && error.name === 'NotAllowedError'
            ? 'cancelled'
            : 'failed';
    }
}

/**
 * Links the device: asks the server for the options of a new key, has the device create it (the person verifies with
 * their fingerprint, face or PIN) and sends it with the agreement accepted. Never throws.
 */
export async function enrollDevice(
    request: EnrollRequest,
    deps: EnrollDeps,
): Promise<EnrollOutcome> {
    try {
        const options = await askOptions(request, deps);

        if (typeof options === 'string') {
            return options;
        }

        const key = await createKey(options, deps);

        if (typeof key === 'string') {
            return key;
        }

        const response = await post(
            request.registerUrl,
            {
                agreement_accepted: true,
                agreement_version: request.agreement,
                device_label: request.label,
                credential: registrationPayload(key),
            },
            deps,
        );

        return response.ok ? 'done' : (BY_STATUS[response.status] ?? 'failed');
    } catch {
        return 'failed';
    }
}

const MESSAGES: Record<Exclude<EnrollOutcome, 'done'>, string> = {
    cancelled:
        'No se creó la llave. Vuelva a intentarlo y confirme con su huella, su rostro o su PIN.',
    failed: 'No se pudo vincular su teléfono. Intente de nuevo.',
    outdated:
        'El acuerdo cambió. Recargue la página para leer la versión nueva.',
    gone: 'Este enlace ya no sirve. Pida a Recursos Humanos un enlace nuevo.',
    unsupported:
        'Este navegador no puede crear llaves de acceso. Abra el enlace en el navegador de su teléfono.',
};

/** What to tell the person when linking did not end well. */
export function enrollMessage(outcome: Exclude<EnrollOutcome, 'done'>): string {
    return MESSAGES[outcome];
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
