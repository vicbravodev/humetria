import { describe, expect, it, vi } from 'vitest';
import type { CreatedKey } from '@/lib/attendance/webauthn';
import {
    AGREEMENT_POINTS,
    agreementSections,
    enrollDevice,
    enrollMessage,
    enrollProblem,
    stepProgress,
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

describe('stepProgress', () => {
    it('says which of the four steps the person is on', () => {
        expect(stepProgress('agreement')).toEqual({
            label: 'Paso 1 de 4',
            value: 1,
            max: 4,
        });
        expect(stepProgress('confirm')?.label).toBe('Paso 2 de 4');
        expect(stepProgress('key')?.label).toBe('Paso 3 de 4');
        expect(stepProgress('done')).toEqual({
            label: 'Paso 4 de 4',
            value: 4,
            max: 4,
        });
    });

    it('shows no steps once the person left the way', () => {
        expect(stepProgress('not-you')).toBeNull();
        expect(stepProgress('later')).toBeNull();
    });
});

describe('AGREEMENT_POINTS', () => {
    it('sums up in three lines what is kept and what is not, before the full agreement', () => {
        expect(AGREEMENT_POINTS).toEqual([
            'Se guardan la hora y el lugar solo en el momento de marcar.',
            'No se sigue su ubicación en ningún otro momento.',
            'Usted puede ver todas sus marcas en «Mi semana».',
        ]);
    });
});

describe('enrollProblem', () => {
    it('says the key was not created, and to confirm with the fingerprint, face or PIN again', () => {
        const problem = enrollProblem('cancelled', 48);

        expect(problem).toMatchObject({
            kind: 'verify',
            title: 'No se creó la llave',
        });
        expect(problem.text).toContain('huella, su rostro o su PIN');
        expect(problem.actions.map(({ kind }) => kind)).toEqual(['retry']);
    });

    it("says a link that stopped working is not the person's fault, and how to get another", () => {
        const problem = enrollProblem('gone', 48);

        expect(problem.title).toBe('Este enlace ya no sirve');
        expect(problem.text).toContain(
            'Venció después de 48 horas o ya se usó',
        );
        expect(problem.text).toContain('No pasó nada con sus datos');
        expect(problem.actions.map(({ kind }) => kind)).toEqual(['copy-hr']);
    });

    it('asks to reload when the agreement changed', () => {
        const problem = enrollProblem('outdated', 48);

        expect(problem.title).toBe('El acuerdo cambió');
        expect(problem.actions.map(({ kind }) => kind)).toEqual(['reload']);
    });

    it.each([
        ['failed', 'No se pudo vincular su teléfono', ['retry']],
        ['unsupported', 'Este navegador no puede crear llaves', []],
    ] as const)('words %s', (outcome, title, actions) => {
        const problem = enrollProblem(outcome, 48);

        expect(problem.title).toBe(title);
        expect(problem.actions.map(({ kind }) => kind)).toEqual(actions);
    });
});
