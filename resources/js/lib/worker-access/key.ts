import type { HttpDeps } from '@/lib/worker-access/http';
import { postJson, promptFailure, redirectOf } from '@/lib/worker-access/http';
import type { CreatedKey } from '@/lib/webauthn';
import { creationOptions, registrationPayload } from '@/lib/webauthn';

/** Why registering a key did not end well. */
export type KeyFailure =
    | 'cancelled'
    | 'refused'
    | 'full'
    | 'forbidden'
    | 'failed'
    | 'unsupported';

/** The key was saved (and where to go), or why not. */
export type KeyOutcome = { redirect: string } | KeyFailure;

export type KeyDeps = HttpDeps & {
    /** `navigator.credentials.create()`, narrowed to a new key or null. */
    createKey: (
        options: PublicKeyCredentialCreationOptions,
    ) => Promise<CreatedKey | null>;
};

const BY_STATUS: Partial<Record<number, KeyFailure>> = {
    403: 'forbidden',
    409: 'full',
    422: 'refused',
};

async function create(
    options: PublicKeyCredentialCreationOptions,
    deps: KeyDeps,
): Promise<CreatedKey | KeyFailure> {
    try {
        return (await deps.createKey(options)) ?? 'failed';
    } catch (error) {
        return promptFailure(error);
    }
}

async function askOptions(
    url: string,
    deps: KeyDeps,
): Promise<PublicKeyCredentialCreationOptions | KeyFailure> {
    const response = await postJson(url, {}, deps);

    if (!response.ok) {
        return BY_STATUS[response.status] ?? 'failed';
    }

    return creationOptions(await response.json()) ?? 'failed';
}

/**
 * Registers a key on this device: asks for the options, has the device create the key (the person verifies with their
 * fingerprint, face or PIN) and sends it with the device's name. Never throws.
 */
export async function registerKey(
    request: { optionsUrl: string; storeUrl: string; label: string },
    deps: KeyDeps,
): Promise<KeyOutcome> {
    try {
        const options = await askOptions(request.optionsUrl, deps);

        if (typeof options === 'string') {
            return options;
        }

        const key = await create(options, deps);

        if (typeof key === 'string') {
            return key;
        }

        const response = await postJson(
            request.storeUrl,
            { label: request.label, credential: registrationPayload(key) },
            deps,
        );

        if (!response.ok) {
            return BY_STATUS[response.status] ?? 'failed';
        }

        const redirect = await redirectOf(response);

        return redirect === null ? 'failed' : { redirect };
    } catch {
        return 'failed';
    }
}

const MESSAGES: Record<KeyFailure, { title: string; text: string }> = {
    cancelled: {
        title: 'No se creó la llave',
        text: 'Vuelva a intentarlo y confirme con su huella, su rostro o su PIN.',
    },
    refused: {
        title: 'No se pudo guardar la llave',
        text: 'Intente de nuevo. Si ya tiene una llave en este dispositivo, entre con ella.',
    },
    full: {
        title: 'Ya tiene 3 llaves',
        text: 'Pida a Recursos Humanos que anule una que ya no use.',
    },
    forbidden: {
        title: 'Aquí no puede crear una llave',
        text: 'Entre con su llave o con el enlace que le envió Recursos Humanos. Pida un enlace nuevo si el suyo venció.',
    },
    unsupported: {
        title: 'Este navegador no puede crear llaves',
        text: 'Abra el enlace en Safari o Chrome de su teléfono.',
    },
    failed: {
        title: 'No hay conexión con el servidor',
        text: 'Revise su señal e intente de nuevo.',
    },
};

/** What the screen says when registering did not end well: what happened and what to do. */
export function keyProblem(failure: KeyFailure): {
    title: string;
    text: string;
} {
    return MESSAGES[failure];
}
