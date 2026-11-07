/**
 * WebAuthn in the browser for a person's linked device: the server speaks JSON with base64url bytes, the browser's
 * `navigator.credentials` takes and gives bytes. Every field from the server is checked, never cast.
 */

/** The key `navigator.credentials.create()` made, as much of it as the server needs. */
export type CreatedKey = {
    rawId: ArrayBuffer;
    type: string;
    response: {
        clientDataJSON: ArrayBuffer;
        attestationObject: ArrayBuffer;
        getTransports?: () => string[];
    };
};

type Json = Record<string, unknown>;

const PUBLIC_KEY: PublicKeyCredentialType = 'public-key';

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The value, when it is one of `allowed`; undefined otherwise. */
function oneOf<T extends string>(
    value: unknown,
    allowed: readonly T[],
): T | undefined {
    return allowed.find((item) => item === value);
}

export function toBase64url(bytes: ArrayBuffer | Uint8Array): string {
    const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    const binary = Array.from(view, (byte) => String.fromCharCode(byte)).join(
        '',
    );

    return btoa(binary)
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replace(/=+$/, '');
}

export function fromBase64url(text: string): Uint8Array<ArrayBuffer> {
    const binary = atob(
        text.replaceAll('-', '+').replaceAll('_', '/').replace(/=+$/, ''),
    );

    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

function relyingParty(value: unknown): PublicKeyCredentialRpEntity | null {
    return isJson(value) &&
        typeof value['id'] === 'string' &&
        typeof value['name'] === 'string'
        ? { id: value['id'], name: value['name'] }
        : null;
}

function user(value: unknown): PublicKeyCredentialUserEntity | null {
    return isJson(value) &&
        typeof value['id'] === 'string' &&
        typeof value['name'] === 'string' &&
        typeof value['displayName'] === 'string'
        ? {
              id: fromBase64url(value['id']),
              name: value['name'],
              displayName: value['displayName'],
          }
        : null;
}

/** The algorithms the server accepts; entries it cannot read are left out. */
function algorithms(value: unknown): PublicKeyCredentialParameters[] | null {
    if (!Array.isArray(value)) {
        return null;
    }

    return value.flatMap((item: unknown) =>
        isJson(item) &&
        item['type'] === PUBLIC_KEY &&
        typeof item['alg'] === 'number'
            ? [{ type: PUBLIC_KEY, alg: item['alg'] }]
            : [],
    );
}

function selection(value: unknown): AuthenticatorSelectionCriteria {
    const json = isJson(value) ? value : {};
    const attachment = oneOf(json['authenticatorAttachment'], [
        'platform',
        'cross-platform',
    ]);
    const residentKey = oneOf(json['residentKey'], [
        'required',
        'preferred',
        'discouraged',
    ]);
    const verification = oneOf(json['userVerification'], [
        'required',
        'preferred',
        'discouraged',
    ]);

    return {
        ...(attachment === undefined
            ? {}
            : { authenticatorAttachment: attachment }),
        ...(residentKey === undefined ? {} : { residentKey }),
        ...(typeof json['requireResidentKey'] === 'boolean'
            ? { requireResidentKey: json['requireResidentKey'] }
            : {}),
        ...(verification === undefined
            ? {}
            : { userVerification: verification }),
    };
}

/** The server's options for a new key, as `navigator.credentials.create()` takes them; null when they are not options. */
export function creationOptions(
    json: unknown,
): PublicKeyCredentialCreationOptions | null {
    if (!isJson(json) || typeof json['challenge'] !== 'string') {
        return null;
    }

    const rp = relyingParty(json['rp']);
    const person = user(json['user']);
    const params = algorithms(json['pubKeyCredParams']);

    if (rp === null || person === null || params === null) {
        return null;
    }

    const attestation = oneOf(json['attestation'], ['none']);

    return {
        challenge: fromBase64url(json['challenge']),
        rp,
        user: person,
        pubKeyCredParams: params,
        authenticatorSelection: selection(json['authenticatorSelection']),
        ...(attestation === undefined ? {} : { attestation }),
        ...(typeof json['timeout'] === 'number'
            ? { timeout: json['timeout'] }
            : {}),
    };
}

/** The new key as the JSON the server reads (the shape of `PublicKeyCredential.toJSON()`). */
export function registrationPayload(key: CreatedKey) {
    const id = toBase64url(key.rawId);

    return {
        id,
        rawId: id,
        type: key.type,
        response: {
            clientDataJSON: toBase64url(key.response.clientDataJSON),
            attestationObject: toBase64url(key.response.attestationObject),
            transports: key.response.getTransports?.() ?? [],
        },
        clientExtensionResults: {},
    };
}

const SYSTEMS: readonly [RegExp, string][] = [
    [/iPhone/, 'iPhone'],
    [/iPad/, 'iPad'],
    [/Android/, 'Android'],
    [/Macintosh|Mac OS X/, 'Mac'],
    [/Windows/, 'Windows'],
];

/** A name for the device RH sees in its list: its system, or «Dispositivo». */
export function deviceLabel(userAgent: string): string {
    return (
        SYSTEMS.find(([pattern]) => pattern.test(userAgent))?.[1] ??
        'Dispositivo'
    );
}
