import { describe, expect, it } from 'vitest';
import {
    creationOptions,
    deviceLabel,
    fromBase64url,
    registrationPayload,
    toBase64url,
} from '@/lib/attendance/webauthn';

const bytes = (...values: number[]) => new Uint8Array(values);

describe('base64url', () => {
    it('writes bytes without padding, with - and _', () => {
        expect(toBase64url(bytes(0xfb, 0xff))).toBe('-_8');
        expect(toBase64url(new TextEncoder().encode('hello'))).toBe('aGVsbG8');
        expect(toBase64url(bytes().buffer)).toBe('');
    });

    it('reads it back, padded or not', () => {
        expect([...fromBase64url('-_8')]).toEqual([0xfb, 0xff]);
        expect(new TextDecoder().decode(fromBase64url('aGVsbG8='))).toBe(
            'hello',
        );
        const random = crypto.getRandomValues(new Uint8Array(57));
        expect([...fromBase64url(toBase64url(random))]).toEqual([...random]);
    });
});

const server = {
    challenge: toBase64url(bytes(1, 2, 3)),
    rp: { id: 'localhost', name: 'Humetría' },
    user: {
        id: toBase64url(new TextEncoder().encode('ana')),
        name: 'Ana',
        displayName: 'Ana López',
    },
    pubKeyCredParams: [
        { type: 'public-key', alg: -7 },
        { type: 'public-key', alg: -257 },
    ],
    authenticatorSelection: {
        authenticatorAttachment: 'platform',
        residentKey: 'required',
        requireResidentKey: true,
        userVerification: 'required',
    },
    attestation: 'none',
    timeout: 60000,
    excludeCredentials: [],
};

describe('creationOptions', () => {
    it("turns the server's options into what navigator.credentials.create() takes", () => {
        const options = creationOptions(server);

        expect(options).toEqual({
            challenge: bytes(1, 2, 3),
            rp: { id: 'localhost', name: 'Humetría' },
            user: {
                id: new TextEncoder().encode('ana'),
                name: 'Ana',
                displayName: 'Ana López',
            },
            pubKeyCredParams: [
                { type: 'public-key', alg: -7 },
                { type: 'public-key', alg: -257 },
            ],
            authenticatorSelection: {
                authenticatorAttachment: 'platform',
                residentKey: 'required',
                requireResidentKey: true,
                userVerification: 'required',
            },
            attestation: 'none',
            timeout: 60000,
        });
    });

    it('leaves out what it does not know, rather than guessing', () => {
        expect(
            creationOptions({
                ...server,
                authenticatorSelection: { userVerification: 'sometimes' },
                attestation: 'weird',
                timeout: 'soon',
                pubKeyCredParams: [
                    { type: 'other', alg: -7 },
                    { type: 'public-key', alg: 'x' },
                    'x',
                ],
            }),
        ).toEqual({
            challenge: bytes(1, 2, 3),
            rp: { id: 'localhost', name: 'Humetría' },
            user: {
                id: new TextEncoder().encode('ana'),
                name: 'Ana',
                displayName: 'Ana López',
            },
            pubKeyCredParams: [],
            authenticatorSelection: {},
        });
    });

    it('gives null for an answer that is not options', () => {
        expect(creationOptions(null)).toBeNull();
        expect(creationOptions('x')).toBeNull();
        expect(creationOptions({ ...server, challenge: 4 })).toBeNull();
        expect(
            creationOptions({ ...server, rp: { name: 'Humetría' } }),
        ).toBeNull();
        expect(creationOptions({ ...server, rp: null })).toBeNull();
        expect(
            creationOptions({ ...server, user: { id: 'YQ', name: 'Ana' } }),
        ).toBeNull();
        expect(
            creationOptions({ ...server, pubKeyCredParams: 'all' }),
        ).toBeNull();
    });
});

describe('registrationPayload', () => {
    const key = {
        rawId: bytes(9, 8).buffer,
        type: 'public-key',
        response: {
            clientDataJSON: bytes(1).buffer,
            attestationObject: bytes(2).buffer,
            getTransports: () => ['internal', 'hybrid'],
        },
    };

    it('writes the new key as the JSON the server reads', () => {
        expect(registrationPayload(key)).toEqual({
            id: 'CQg',
            rawId: 'CQg',
            type: 'public-key',
            response: {
                clientDataJSON: 'AQ',
                attestationObject: 'Ag',
                transports: ['internal', 'hybrid'],
            },
            clientExtensionResults: {},
        });
    });

    it('sends no transports when the browser cannot tell them', () => {
        const { getTransports: _ignored, ...response } = key.response;

        expect(
            registrationPayload({ ...key, response }).response.transports,
        ).toEqual([]);
    });
});

describe('deviceLabel', () => {
    it('names the device by its system', () => {
        expect(
            deviceLabel(
                'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)',
            ),
        ).toBe('iPhone');
        expect(
            deviceLabel('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'),
        ).toBe('iPad');
        expect(deviceLabel('Mozilla/5.0 (Linux; Android 14; Pixel 8)')).toBe(
            'Android',
        );
        expect(
            deviceLabel('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)'),
        ).toBe('Mac');
        expect(deviceLabel('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')).toBe(
            'Windows',
        );
        expect(deviceLabel('Mozilla/5.0 (X11; Linux x86_64)')).toBe(
            'Dispositivo',
        );
    });
});
