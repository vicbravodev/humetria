import { describe, expect, it, vi } from 'vitest';
import { keyProblem, registerKey } from '@/lib/worker-access/key';
import type { CreatedKey } from '@/lib/webauthn';

const options = {
    challenge: 'AQID',
    rp: { id: 'localhost', name: 'Humetría' },
    user: { id: 'YW5h', name: 'Ana', displayName: 'Ana' },
    pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
};

const created: CreatedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        attestationObject: new Uint8Array([2]).buffer,
    },
};

const request = {
    optionsUrl: '/me/acme/key/options',
    storeUrl: '/me/acme/key',
    label: 'iPhone',
};

const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), { status });

function server(...answers: Response[]) {
    const fetcher = vi.fn<typeof fetch>();
    answers.forEach((answer) => fetcher.mockResolvedValueOnce(answer));

    return fetcher;
}

describe('registerKey', () => {
    it('creates the key, sends it with the device name and follows where the server says', async () => {
        const fetcher = server(json(options), json({ redirect: '/me/acme' }));

        const outcome = await registerKey(request, {
            fetcher,
            createKey: vi.fn().mockResolvedValue(created),
            xsrf: null,
        });

        expect(outcome).toEqual({ redirect: '/me/acme' });
        const body = fetcher.mock.calls[1]?.[1]?.body;
        const sent: unknown =
            typeof body === 'string' ? JSON.parse(body) : null;
        expect(sent).toMatchObject({
            label: 'iPhone',
            credential: { id: 'CQ' },
        });
    });

    it('tells why the server did not take it', async () => {
        const register = (fetcher: typeof fetch) =>
            registerKey(request, {
                fetcher,
                createKey: vi.fn().mockResolvedValue(created),
                xsrf: null,
            });

        expect(await register(server(json({}, 403)))).toBe('forbidden');
        expect(await register(server(json({}, 409)))).toBe('full');
        expect(await register(server(json(options), json({}, 422)))).toBe(
            'refused',
        );
        expect(await register(server(json({}, 503)))).toBe('server');
        expect(await register(server(json(options), json({}, 500)))).toBe(
            'server',
        );
        expect(await register(server(json(options), json({})))).toBe('failed');
        expect(await register(server(json({ nope: 1 })))).toBe('failed');
        expect(
            await register(
                vi.fn<typeof fetch>().mockRejectedValue(new Error()),
            ),
        ).toBe('failed');
    });

    it('says the person cancelled, or the device gave nothing', async () => {
        const register = (createKey: () => Promise<CreatedKey | null>) =>
            registerKey(request, {
                fetcher: server(json(options)),
                createKey,
                xsrf: null,
            });

        expect(
            await register(() =>
                Promise.reject(new DOMException('no', 'NotAllowedError')),
            ),
        ).toBe('cancelled');
        expect(await register(() => Promise.resolve(null))).toBe('failed');
    });

    it('says what happened and what to do for every failure', () => {
        expect(keyProblem('full').title).toBe('Ya tiene 3 llaves');
        expect(keyProblem('forbidden').text).toContain('enlace');
        expect(keyProblem('unsupported').text).toContain('Safari o Chrome');
        expect(keyProblem('cancelled').text).toContain('huella');
        expect(keyProblem('refused').title).toContain('guardar');
        expect(keyProblem('failed').text).toContain('señal');
        expect(keyProblem('server')).toEqual({
            title: 'Algo salió mal de nuestro lado',
            text: 'Si ya ve su llave en el dispositivo, intente entrar con ella; si no, vuelva a intentarlo en unos minutos.',
        });
    });
});
