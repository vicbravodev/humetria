import { describe, expect, it, vi } from 'vitest';
import { subscribeForPush } from '@/lib/notifications/push';
import {
    KEY,
    json,
    nothing,
    registration,
    server,
    subscription,
} from '../../support/push-phone';

describe('subscribeForPush', () => {
    it("asks the key, subscribes with it and tells the server, with the person's session", async () => {
        const phone = registration();
        const fetcher = server(json({ publicKey: KEY }), nothing(204));

        const result = await subscribeForPush('acme', phone, {
            fetcher,
            xsrf: 'token',
        });

        expect(result).toBe('subscribed');
        expect(phone.pushManager.subscribe).toHaveBeenCalledWith({
            userVisibleOnly: true,
            applicationServerKey: expect.any(Uint8Array),
        });
        expect(fetcher).toHaveBeenNthCalledWith(
            1,
            '/me/acme/push',
            expect.objectContaining({
                method: 'GET',
                credentials: 'same-origin',
            }),
        );
        expect(fetcher).toHaveBeenNthCalledWith(
            2,
            '/me/acme/push',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'token' }),
                body: JSON.stringify({
                    endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
                    keys: { p256dh: 'p', auth: 'a' },
                }),
            }),
        );
    });

    it('sends no token when the browser has none', async () => {
        const fetcher = server(json({ publicKey: KEY }), nothing(204));

        await subscribeForPush('acme', registration(), { fetcher, xsrf: null });

        expect(fetcher.mock.calls[1]?.[1]?.headers).not.toHaveProperty(
            'X-XSRF-TOKEN',
        );
    });

    it('does not subscribe when the server has no keys: the push is off', async () => {
        const phone = registration();
        const fetcher = server(json({ publicKey: null }));

        expect(
            await subscribeForPush('acme', phone, { fetcher, xsrf: null }),
        ).toBe('unavailable');
        expect(phone.pushManager.subscribe).not.toHaveBeenCalled();
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('keeps the subscription it already has when it was made with the same key, and still tells the server', async () => {
        const existing = subscription();
        const phone = registration(existing);
        const fetcher = server(json({ publicKey: KEY }), nothing(204));

        expect(
            await subscribeForPush('acme', phone, { fetcher, xsrf: null }),
        ).toBe('subscribed');
        expect(phone.pushManager.subscribe).not.toHaveBeenCalled();
        expect(existing.unsubscribe).not.toHaveBeenCalled();
        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it.each([
        [
            'another key (the keys were rotated)',
            Uint8Array.from({ length: 65 }, () => 7),
        ],
        ['no key at all', null],
        [
            'a key of another size',
            Uint8Array.from({ length: 64 }, (_, index) => index),
        ],
    ])(
        'drops a subscription made with %s and makes a new one',
        async (_case, old) => {
            const existing = subscription(
                'https://fcm.googleapis.com/fcm/send/old',
                old,
            );
            const phone = registration(existing);
            const fetcher = server(json({ publicKey: KEY }), nothing(204));

            expect(
                await subscribeForPush('acme', phone, { fetcher, xsrf: null }),
            ).toBe('subscribed');
            expect(existing.unsubscribe).toHaveBeenCalledOnce();
            expect(phone.pushManager.subscribe).toHaveBeenCalledOnce();
        },
    );
});

describe('subscribeForPush, when something fails', () => {
    it('fails, without telling the server, when the browser refuses to subscribe', async () => {
        const phone = registration();
        phone.pushManager.subscribe.mockRejectedValue(
            new DOMException('no', 'NotAllowedError'),
        );
        const fetcher = server(json({ publicKey: KEY }));

        expect(
            await subscribeForPush('acme', phone, { fetcher, xsrf: null }),
        ).toBe('failed');
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('fails when the subscription is not one the server would take', async () => {
        const phone = registration();
        phone.pushManager.subscribe.mockResolvedValue(
            subscription('http://fcm.googleapis.com/x'),
        );
        const fetcher = server(json({ publicKey: KEY }));

        expect(
            await subscribeForPush('acme', phone, { fetcher, xsrf: null }),
        ).toBe('failed');
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it.each([422, 500, 401])(
        'fails when the server answers %s to the subscription',
        async (status) => {
            const fetcher = server(json({ publicKey: KEY }), nothing(status));

            expect(
                await subscribeForPush('acme', registration(), {
                    fetcher,
                    xsrf: null,
                }),
            ).toBe('failed');
        },
    );

    it('fails when the key cannot be asked: the server answers badly or the network is down', async () => {
        const down = vi
            .fn<typeof fetch>()
            .mockRejectedValue(new TypeError('network'));
        const ask = (fetcher: typeof fetch) =>
            subscribeForPush('acme', registration(), { fetcher, xsrf: null });

        expect(await ask(server(nothing(500)))).toBe('failed');
        expect(await ask(server(json({ other: 1 })))).toBe('failed');
        expect(await ask(down)).toBe('failed');
    });
});
