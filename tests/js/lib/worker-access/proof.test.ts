import type { PendingVisit } from '@inertiajs/core';
import { describe, expect, it, vi } from 'vitest';
import {
    checkProofCode,
    codeMessage,
    codeSentMessage,
    pendingSend,
    proofKeyMessage,
    sendProofCode,
} from '@/lib/worker-access/proof';

function server(...answers: Response[]) {
    const fetcher = vi.fn<typeof fetch>();
    answers.forEach((answer) => fetcher.mockResolvedValueOnce(answer));

    return fetcher;
}

const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), { status });

describe('sendProofCode', () => {
    it('asks for a code with the CSRF token', async () => {
        const fetcher = server(json({ sent: true }));

        expect(
            await sendProofCode('/me/acme/proof/code', { fetcher, xsrf: 't' }),
        ).toBe('sent');
        expect(fetcher).toHaveBeenCalledWith(
            '/me/acme/proof/code',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 't' }),
            }),
        );
    });

    it('says when too many codes were asked for, and when there is no signal', async () => {
        const deps = (answer: Response) => ({
            fetcher: server(answer),
            xsrf: null,
        });

        expect(await sendProofCode('/c', deps(json({}, 429)))).toBe('busy');
        expect(await sendProofCode('/c', deps(json({}, 403)))).toBe('failed');
        expect(
            await sendProofCode('/c', {
                fetcher: vi.fn<typeof fetch>().mockRejectedValue(new Error()),
                xsrf: null,
            }),
        ).toBe('failed');
    });
});

describe('checkProofCode', () => {
    const check = (answer: Response) =>
        checkProofCode('/c/check', '123456', {
            fetcher: server(answer),
            xsrf: null,
        });

    it('follows where the server says once the code is right', async () => {
        const fetcher = server(json({ redirect: '/me/acme/support' }));

        expect(
            await checkProofCode('/c/check', '123456', { fetcher, xsrf: null }),
        ).toEqual({ redirect: '/me/acme/support' });
        expect(fetcher).toHaveBeenCalledWith(
            '/c/check',
            expect.objectContaining({
                body: JSON.stringify({ code: '123456' }),
            }),
        );
    });

    it('tells why a code did not prove it', async () => {
        expect(await check(json({ reason: 'wrong' }, 422))).toBe('wrong');
        expect(await check(json({ reason: 'expired' }, 422))).toBe('expired');
        expect(await check(json({ reason: 'locked' }, 422))).toBe('locked');
        expect(await check(json({ reason: 'other' }, 422))).toBe('invalid');
        expect(await check(json({ errors: { code: ['x'] } }, 422))).toBe(
            'invalid',
        );
        expect(await check(json({}, 429))).toBe('busy');
        expect(await check(json({}, 403))).toBe('failed');
        expect(await check(json({}))).toBe('failed');
    });

    it('never throws without a signal', async () => {
        expect(
            await checkProofCode('/c/check', '123456', {
                fetcher: vi.fn<typeof fetch>().mockRejectedValue(new Error()),
                xsrf: null,
            }),
        ).toBe('failed');
    });
});

describe('messages', () => {
    it('says what to do after each failure, in usted', () => {
        expect(codeMessage('wrong')).toContain('no es correcto');
        expect(codeMessage('expired')).toContain('venció');
        expect(codeMessage('locked')).toContain('demasiados intentos');
        expect(codeMessage('invalid')).toContain('seis números');
        expect(codeMessage('busy')).toContain('Espere');
        expect(codeMessage('failed')).toContain('señal');
    });

    it('says what to do when the key did not prove it, never to ask for a link', () => {
        expect(proofKeyMessage('cancelled')).toContain('Vuelva a intentarlo');
        expect(proofKeyMessage('refused')).toContain('No reconocimos');
        expect(proofKeyMessage('unsupported')).toContain('donde creó su llave');
        expect(proofKeyMessage('failed')).toContain('señal');
    });

    it('says how long a code lasts', () => {
        expect(codeSentMessage(10)).toBe(
            'Le enviamos un código de seis números a su correo. Vence en 10 minutos.',
        );
    });
});

describe('pendingSend', () => {
    const visit = (method: PendingVisit['method']) => ({
        url: new URL('http://localhost/me/acme/vacations/requests'),
        method,
        data: { days: 3 },
    });

    it('keeps a form to send it again, never a page', () => {
        expect(pendingSend(visit('post'))).toEqual({
            url: new URL('http://localhost/me/acme/vacations/requests'),
            method: 'post',
            data: { days: 3 },
        });
        expect(pendingSend(visit('get'))).toBeNull();
    });
});
