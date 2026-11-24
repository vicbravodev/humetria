import { describe, expect, it, vi } from 'vitest';
import { forgetPush } from '@/lib/notifications/push';
import type { PushRegistration } from '@/lib/notifications/push';
import {
    nothing,
    registration,
    server,
    subscription,
} from '../../support/push-phone';

function container(phone: PushRegistration | undefined) {
    return { getRegistration: vi.fn().mockResolvedValue(phone) };
}

describe('forgetPush', () => {
    it('tells the server which phone to forget and then drops the subscription', async () => {
        const existing = subscription();
        const fetcher = server(nothing(204));

        await forgetPush('acme', {
            container: container(registration(existing)),
            fetcher,
            xsrf: 'token',
        });

        expect(fetcher).toHaveBeenCalledWith(
            '/me/acme/push',
            expect.objectContaining({
                method: 'DELETE',
                credentials: 'same-origin',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'token' }),
                body: JSON.stringify({
                    endpoint: 'https://fcm.googleapis.com/fcm/send/abc',
                }),
            }),
        );
        expect(existing.unsubscribe).toHaveBeenCalledOnce();
    });

    it('looks for the worker of its own company', async () => {
        const where = container(registration());

        await forgetPush('acme', {
            container: where,
            fetcher: server(),
            xsrf: null,
        });

        expect(where.getRegistration).toHaveBeenCalledWith('/me/acme');
    });

    it('does nothing where there is no worker, no push or no subscription', async () => {
        const fetcher = server();

        await forgetPush('acme', { container: undefined, fetcher, xsrf: null });
        await forgetPush('acme', {
            container: container(undefined),
            fetcher,
            xsrf: null,
        });
        await forgetPush('acme', {
            container: container(registration(null)),
            fetcher,
            xsrf: null,
        });

        expect(fetcher).not.toHaveBeenCalled();
    });

    it('drops the subscription even when the server cannot be reached, and never fails: leaving must not wait on it', async () => {
        const existing = subscription();
        const down = vi
            .fn<typeof fetch>()
            .mockRejectedValue(new TypeError('network'));

        await expect(
            forgetPush('acme', {
                container: container(registration(existing)),
                fetcher: down,
                xsrf: null,
            }),
        ).resolves.toBeUndefined();

        expect(existing.unsubscribe).toHaveBeenCalledOnce();
    });

    it('does not fail when the browser cannot drop it, nor when it cannot even look', async () => {
        const stuck = subscription();
        stuck.unsubscribe.mockRejectedValue(new Error('no'));
        const broken = {
            getRegistration: vi.fn().mockRejectedValue(new Error('no')),
        };

        await expect(
            forgetPush('acme', {
                container: container(registration(stuck)),
                fetcher: server(nothing(204)),
                xsrf: null,
            }),
        ).resolves.toBeUndefined();
        await expect(
            forgetPush('acme', {
                container: broken,
                fetcher: server(),
                xsrf: null,
            }),
        ).resolves.toBeUndefined();
    });
});
