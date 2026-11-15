import { describe, expect, it, vi } from 'vitest';
import { startAnalytics, trackLead } from '@/lib/analytics';
import {
    HOST,
    LANDING_BLOCK,
    TOKEN,
    documentWith,
    engine,
    memoryStorage,
} from '../support/analytics';

const ID = '9d3f6a52-8a7c-4d49-9d0e-5f3a1b2c4d5e';

async function started() {
    const posthog = engine();

    await startAnalytics(documentWith(LANDING_BLOCK), () =>
        Promise.resolve(posthog),
    );
    posthog.capture.mockClear();

    return posthog;
}

describe('startAnalytics', () => {
    it('does not even load PostHog when the page has no block', async () => {
        const load = vi.fn();

        await startAnalytics(documentWith(null), load);

        expect(load).not.toHaveBeenCalled();
    });

    it('loads PostHog, starts it with the privacy options and reports the page view once', async () => {
        const posthog = engine();

        await startAnalytics(documentWith(LANDING_BLOCK), () =>
            Promise.resolve(posthog),
        );

        expect(posthog.init).toHaveBeenCalledTimes(1);
        expect(posthog.init).toHaveBeenCalledWith(
            TOKEN,
            expect.objectContaining({ autocapture: false, api_host: HOST }),
        );
        expect(posthog.capture).toHaveBeenCalledTimes(1);
        expect(posthog.capture).toHaveBeenCalledWith('$pageview');
    });
});

describe('trackLead', () => {
    it('reports generate_lead once, with no parameters, and remembers the id', async () => {
        const posthog = await started();
        const storage = memoryStorage();

        trackLead(storage, ID);

        expect(posthog.capture).toHaveBeenCalledTimes(1);
        expect(posthog.capture).toHaveBeenCalledWith('generate_lead');
        expect([...storage.values.values()]).toEqual([ID]);
    });

    it('does nothing when PostHog was never started, and remembers nothing', async () => {
        await startAnalytics(documentWith(null), vi.fn());
        const storage = memoryStorage();

        trackLead(storage, ID);

        expect(storage.values.size).toBe(0);
    });

    it.each([
        ['undefined', undefined],
        ['empty', ''],
    ])(
        'does nothing without the id of a stored request (%s)',
        async (_name, id) => {
            const posthog = await started();
            const storage = memoryStorage();

            trackLead(storage, id);

            expect(posthog.capture).not.toHaveBeenCalled();
            expect(storage.values.size).toBe(0);
        },
    );

    it('never reports the same request twice: a reload or a back does not repeat it', async () => {
        const posthog = await started();
        const storage = memoryStorage();

        trackLead(storage, ID);
        trackLead(storage, ID);
        trackLead(storage, '11111111-2222-4333-8444-555555555555');

        expect(posthog.capture).toHaveBeenCalledTimes(2);
    });
});
