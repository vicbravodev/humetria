import { describe, expect, it, vi } from 'vitest';
import { registerDeviceKey } from '@/lib/worker-access/device-key-registration';
import { fromBase64url, toBase64url } from '@/lib/webauthn';

const urls = {
    optionsUrl: '/me/acme/device-key/options',
    storeUrl: '/me/acme/device-key',
};
const challenge = new Uint8Array(32).map((_, i) => i + 1);
const pass = {
    deviceKeyId: 'k-1',
    pass: 'v1.p.m',
    passId: 'p-1',
    passExpiresAt: '2026-11-26T09:00:00-06:00',
};

type Step = { status: number; body: unknown };

/** A server that answers `options` and then `store` with these. */
function server(options: Step, store: Step) {
    return vi.fn<typeof fetch>((url) => {
        const step = url === urls.optionsUrl ? options : store;

        return Promise.resolve(
            new Response(JSON.stringify(step.body), { status: step.status }),
        );
    });
}

const optionsOk: Step = {
    status: 200,
    body: { challenge: toBase64url(challenge) },
};

const deps = (fetcher: typeof fetch) => ({
    fetcher,
    xsrf: 'token',
    subtle: crypto.subtle,
    label: 'iPhone',
});

/** The JSON body a request carried. */
function bodyOf(init: RequestInit | undefined): Record<string, unknown> {
    const sent: unknown =
        typeof init?.body === 'string' ? JSON.parse(init.body) : null;

    if (typeof sent !== 'object' || sent === null) {
        throw new Error('The request carried no JSON.');
    }

    return Object.fromEntries(Object.entries(sent));
}

describe('registerDeviceKey, when it goes through', () => {
    it('gets the pass and keeps a key that cannot be exported', async () => {
        const fetcher = server(optionsOk, { status: 201, body: pass });

        const outcome = await registerDeviceKey(urls, deps(fetcher));

        expect(outcome).toMatchObject({ kind: 'ready', ...pass });
        expect(outcome.kind === 'ready' && outcome.key.extractable).toBe(false);
        expect(fetcher.mock.calls[1]?.[1]?.headers).toMatchObject({
            'X-XSRF-TOKEN': 'token',
        });
    });

    it('proves it has the key with a signature over the 32 bytes of the challenge', async () => {
        const fetcher = server(optionsOk, { status: 201, body: pass });

        await registerDeviceKey(urls, deps(fetcher));

        const sent = bodyOf(fetcher.mock.calls[1]?.[1]);
        const publicKey = await crypto.subtle.importKey(
            'spki',
            fromBase64url(String(sent['public_key'])),
            { name: 'ECDSA', namedCurve: 'P-256' },
            false,
            ['verify'],
        );

        expect(sent).toMatchObject({
            challenge: toBase64url(challenge),
            label: 'iPhone',
        });
        expect(
            await crypto.subtle.verify(
                { name: 'ECDSA', hash: 'SHA-256' },
                publicKey,
                fromBase64url(String(sent['signature'])),
                challenge,
            ),
        ).toBe(true);
    });
});

describe('registerDeviceKey, when it does not', () => {
    it('asks for a recent proof when the server wants one, at either step', async () => {
        expect(
            await registerDeviceKey(
                urls,
                deps(
                    server(
                        { status: 403, body: { proofRequired: true } },
                        optionsOk,
                    ),
                ),
            ),
        ).toEqual({ kind: 'proof' });
        expect(
            await registerDeviceKey(
                urls,
                deps(
                    server(optionsOk, {
                        status: 403,
                        body: { proofRequired: true },
                    }),
                ),
            ),
        ).toEqual({ kind: 'proof' });
    });

    it('says the session ended on a 401', async () => {
        expect(
            await registerDeviceKey(
                urls,
                deps(server({ status: 401, body: {} }, optionsOk)),
            ),
        ).toEqual({ kind: 'session' });
        expect(
            await registerDeviceKey(
                urls,
                deps(server(optionsOk, { status: 401, body: {} })),
            ),
        ).toEqual({ kind: 'session' });
    });

    it('fails on anything else: a 403 that is not about the proof, 422, 5xx, a pass without its id, a challenge it cannot read, no network', async () => {
        const steps: [Step, Step][] = [
            [{ status: 403, body: { message: 'no' } }, optionsOk],
            [optionsOk, { status: 422, body: { message: 'no' } }],
            [optionsOk, { status: 500, body: {} }],
            [optionsOk, { status: 201, body: { ...pass, passId: undefined } }],
            [{ status: 200, body: { challenge: 7 } }, optionsOk],
            [{ status: 500, body: {} }, optionsOk],
        ];

        for (const [options, store] of steps) {
            expect(
                await registerDeviceKey(urls, deps(server(options, store))),
            ).toEqual({ kind: 'failed' });
        }

        expect(
            await registerDeviceKey(
                urls,
                deps(() => Promise.reject(new TypeError('Failed to fetch'))),
            ),
        ).toEqual({ kind: 'failed' });
    });

    it('fails when the answer of the server is not JSON', async () => {
        expect(
            await registerDeviceKey(
                urls,
                deps(() =>
                    Promise.resolve(new Response('<html>', { status: 403 })),
                ),
            ),
        ).toEqual({ kind: 'failed' });
    });
});
