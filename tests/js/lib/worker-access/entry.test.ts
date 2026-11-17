import { describe, expect, it, vi } from 'vitest';
import {
    enterWithKey,
    entryMessage,
    linkRequestMessage,
    requestLink,
} from '@/lib/worker-access/entry';
import type { SignedKey } from '@/lib/webauthn';

const options = { challenge: 'AQID', rpId: 'localhost' };

const signed: SignedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        authenticatorData: new Uint8Array([2]).buffer,
        signature: new Uint8Array([3]).buffer,
        userHandle: null,
    },
};

const urls = {
    optionsUrl: '/me/acme/enter/options',
    enterUrl: '/me/acme/enter',
};

function server(...answers: Response[]) {
    const fetcher = vi.fn<typeof fetch>();
    answers.forEach((answer) => fetcher.mockResolvedValueOnce(answer));

    return fetcher;
}

const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), { status });

describe('enterWithKey', () => {
    it('signs the challenge and follows where the server says', async () => {
        const fetcher = server(json(options), json({ redirect: '/me/acme' }));
        const getKey = vi.fn().mockResolvedValue(signed);

        const outcome = await enterWithKey(urls, {
            fetcher,
            getKey,
            xsrf: 't',
        });

        expect(outcome).toEqual({ redirect: '/me/acme' });
        expect(fetcher).toHaveBeenLastCalledWith(
            '/me/acme/enter',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 't' }),
            }),
        );
    });

    it('says the key was refused when the server does not verify it', async () => {
        const getKey = vi.fn().mockResolvedValue(signed);

        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options), json({}, 422)),
                getKey,
                xsrf: null,
            }),
        ).toBe('refused');
    });

    it('says the person cancelled when they closed the prompt', async () => {
        const getKey = vi
            .fn()
            .mockRejectedValue(new DOMException('no', 'NotAllowedError'));

        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options)),
                getKey,
                xsrf: null,
            }),
        ).toBe('cancelled');
    });

    it('fails without a signal, with options that are not options, or a device that gives nothing', async () => {
        const getKey = vi.fn().mockResolvedValue(null);
        const fetcher = vi.fn<typeof fetch>().mockRejectedValue(new Error());

        expect(await enterWithKey(urls, { fetcher, getKey, xsrf: null })).toBe(
            'failed',
        );
        expect(
            await enterWithKey(urls, {
                fetcher: server(json({}, 500)),
                getKey,
                xsrf: null,
            }),
        ).toBe('server');
        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options), json({}, 502)),
                getKey: vi.fn().mockResolvedValue(signed),
                xsrf: null,
            }),
        ).toBe('server');
        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options)),
                getKey,
                xsrf: null,
            }),
        ).toBe('failed');
        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options), json({})),
                getKey: vi.fn().mockResolvedValue(signed),
                xsrf: null,
            }),
        ).toBe('failed');
        expect(
            await enterWithKey(urls, {
                fetcher: server(json(options)),
                getKey: vi.fn().mockRejectedValue(new Error()),
                xsrf: null,
            }),
        ).toBe('failed');
    });

    it('has a short message for every failure', () => {
        expect(entryMessage('cancelled')).toContain('huella');
        expect(entryMessage('refused')).toContain('enlace por correo');
        expect(entryMessage('unsupported')).toContain('enlace por correo');
        expect(entryMessage('failed')).toContain('señal');
        expect(entryMessage('server')).toBe(
            'Algo salió mal de nuestro lado. Vuelva a intentarlo en unos minutos.',
        );
    });
});

describe('requestLink', () => {
    it("says the same whether the email is anyone's or not", async () => {
        expect(
            await requestLink('/me/acme/enter/link', 'ana@acme.mx', {
                fetcher: server(json({})),
                xsrf: null,
            }),
        ).toBe('sent');
        expect(linkRequestMessage('sent')).toContain('Si ese correo');
    });

    it('tells an invalid email, too many tries and no signal apart', async () => {
        const ask = (fetcher: typeof fetch) =>
            requestLink('/x', 'x', { fetcher, xsrf: null });

        expect(await ask(server(json({}, 422)))).toBe('invalid');
        expect(await ask(server(json({}, 429)))).toBe('busy');
        expect(await ask(server(json({}, 500)))).toBe('server');
        expect(
            await ask(vi.fn<typeof fetch>().mockRejectedValue(new Error())),
        ).toBe('failed');
        expect(linkRequestMessage('invalid')).toContain('correo válido');
        expect(linkRequestMessage('busy')).toContain('Espere');
        expect(linkRequestMessage('failed')).toContain('señal');
        expect(linkRequestMessage('server')).toContain('de nuestro lado');
    });
});
