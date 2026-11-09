import { describe, expect, it, vi } from 'vitest';
import type { CreatedKey } from '@/lib/attendance/webauthn';
import {
    agreementSections,
    enrollDevice,
    enrollMessage,
} from '@/lib/attendance/enroll';

const options = {
    challenge: 'AQID',
    rp: { id: 'localhost', name: 'Humetría' },
    user: { id: 'YW5h', name: 'Ana', displayName: 'Ana' },
    pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
};

const key: CreatedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        attestationObject: new Uint8Array([2]).buffer,
    },
};

const request = {
    optionsUrl: '/a/acme/enroll/tok/options',
    registerUrl: '/a/acme/enroll/tok/register',
    agreement: 3,
    label: 'iPhone',
};

/** A server that answers the options, then the key, with these statuses. */
function serverAnswering(
    optionsStatus: number,
    registerStatus: number,
    optionsBody: unknown = options,
) {
    return vi.fn<typeof fetch>((url) =>
        Promise.resolve(
            url === request.optionsUrl
                ? new Response(JSON.stringify(optionsBody), {
                      status: optionsStatus,
                  })
                : new Response('{}', { status: registerStatus }),
        ),
    );
}

const makesKey = () => Promise.resolve(key);

describe('enrollDevice', () => {
    it('asks for the options, creates the key and sends it with the agreement accepted', async () => {
        const fetcher = serverAnswering(200, 200);
        const createKey = vi.fn(makesKey);

        await expect(
            enrollDevice(request, { fetcher, createKey, xsrf: 'tok-xsrf' }),
        ).resolves.toBe('done');

        expect(createKey).toHaveBeenCalledWith(
            expect.objectContaining({ challenge: new Uint8Array([1, 2, 3]) }),
        );
        const [optionsCall, registerCall] = fetcher.mock.calls;
        expect(optionsCall?.[0]).toBe(request.optionsUrl);
        expect(optionsCall?.[1]).toMatchObject({
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': 'tok-xsrf',
            },
        });
        expect(registerCall?.[0]).toBe(request.registerUrl);
        const body = registerCall?.[1]?.body;
        expect(typeof body === 'string' ? JSON.parse(body) : null).toEqual({
            agreement_accepted: true,
            agreement_version: 3,
            device_label: 'iPhone',
            credential: {
                id: 'CQ',
                rawId: 'CQ',
                type: 'public-key',
                response: {
                    clientDataJSON: 'AQ',
                    attestationObject: 'Ag',
                    transports: [],
                },
                clientExtensionResults: {},
            },
        });
    });

    it('sends no CSRF header when there is no cookie', async () => {
        const fetcher = serverAnswering(200, 200);

        await enrollDevice(request, {
            fetcher,
            createKey: makesKey,
            xsrf: null,
        });

        expect(fetcher.mock.calls[0]?.[1]?.headers).not.toHaveProperty(
            'X-XSRF-TOKEN',
        );
    });
});

describe('enrollDevice, when the server does not go along', () => {
    it.each([
        { when: 'the link no longer works', at: [404, 200], outcome: 'gone' },
        { when: 'the server fails', at: [500, 200], outcome: 'failed' },
        { when: 'the key is refused', at: [200, 422], outcome: 'failed' },
        { when: 'the agreement changed', at: [200, 409], outcome: 'outdated' },
        { when: 'the link died meanwhile', at: [200, 404], outcome: 'gone' },
    ])(
        'tells when $when',
        async ({ at: [first = 200, second = 200], outcome }) => {
            await expect(
                enrollDevice(request, {
                    fetcher: serverAnswering(first, second),
                    createKey: makesKey,
                    xsrf: null,
                }),
            ).resolves.toBe(outcome);
        },
    );

    it.each([
        {
            when: 'the answer is not options',
            answer: () => new Response('{"nothing":true}'),
        },
        {
            when: 'the answer is not JSON',
            answer: () => new Response('<html>'),
        },
    ])('tells when $when', async ({ answer }) => {
        const fetcher = vi.fn<typeof fetch>(() => Promise.resolve(answer()));

        await expect(
            enrollDevice(request, { fetcher, createKey: makesKey, xsrf: null }),
        ).resolves.toBe('failed');
    });

    it('tells when the network fails', async () => {
        const fetcher = vi.fn<typeof fetch>(() =>
            Promise.reject(new TypeError('offline')),
        );

        await expect(
            enrollDevice(request, { fetcher, createKey: makesKey, xsrf: null }),
        ).resolves.toBe('failed');
    });
});

describe('enrollDevice, when the device does not go along', () => {
    it.each([
        {
            when: 'the person cancelled or did not verify',
            createKey: () =>
                Promise.reject(new DOMException('no', 'NotAllowedError')),
            outcome: 'cancelled',
        },
        {
            when: 'the device failed',
            createKey: () =>
                Promise.reject(new DOMException('no', 'InvalidStateError')),
            outcome: 'failed',
        },
        {
            when: 'the device gave nothing',
            createKey: () => Promise.resolve(null),
            outcome: 'failed',
        },
    ])('tells when $when, sending nothing', async ({ createKey, outcome }) => {
        const fetcher = serverAnswering(200, 200);

        await expect(
            enrollDevice(request, { fetcher, createKey, xsrf: null }),
        ).resolves.toBe(outcome);
        expect(fetcher).toHaveBeenCalledTimes(1);
    });
});

describe('enrollMessage', () => {
    it('says each outcome in «usted»', () => {
        expect(enrollMessage('cancelled')).toContain('Vuelva a intentarlo');
        expect(enrollMessage('outdated')).toContain('Recargue la página');
        expect(enrollMessage('gone')).toContain(
            'Pida a Recursos Humanos un enlace nuevo',
        );
        expect(enrollMessage('failed')).toContain('Intente de nuevo');
        expect(enrollMessage('unsupported')).toContain(
            'no puede crear llaves de acceso',
        );
    });
});

describe('agreementSections', () => {
    it('splits the agreement into its sections, each with its title', () => {
        expect(
            agreementSections(
                'Qué se guarda. La hora.\n\nQuién lo ve. Usted.\n\nSin título',
            ),
        ).toEqual([
            { title: 'Qué se guarda', text: 'La hora.' },
            { title: 'Quién lo ve', text: 'Usted.' },
            { title: '', text: 'Sin título' },
        ]);
    });
});
