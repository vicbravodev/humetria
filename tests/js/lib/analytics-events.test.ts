import { describe, expect, it } from 'vitest';
import {
    captureError,
    startAnalytics,
    track,
    trackFormErrors,
} from '@/lib/analytics';
import { LANDING_BLOCK, documentWith, engine } from '../support/analytics';

async function started() {
    const posthog = engine();

    await startAnalytics(documentWith(LANDING_BLOCK), () =>
        Promise.resolve(posthog),
    );
    posthog.capture.mockClear();

    return posthog;
}

describe('track', () => {
    it('reports the event with its cleaned properties', async () => {
        const posthog = await started();

        track('cta_click', { target: 'hero_demo', email: 'ana@x.mx' });

        expect(posthog.capture).toHaveBeenCalledWith('cta_click', {
            target: 'hero_demo',
        });
    });

    it('does nothing when PostHog never started: no token, no consent', async () => {
        const posthog = engine();

        await startAnalytics(documentWith(null), () =>
            Promise.resolve(posthog),
        );
        track('cta_click', { target: 'hero_demo' });

        expect(posthog.capture).not.toHaveBeenCalled();
    });
});

describe('captureError', () => {
    it('hands the error to PostHog error tracking', async () => {
        const posthog = await started();
        const error = new Error('boom');

        captureError(error);

        expect(posthog.captureException).toHaveBeenCalledWith(error);
    });

    it('does nothing when PostHog never started', async () => {
        const posthog = engine();

        await startAnalytics(documentWith(null), () =>
            Promise.resolve(posthog),
        );
        captureError(new Error('boom'));

        expect(posthog.captureException).not.toHaveBeenCalled();
    });
});

describe('trackFormErrors', () => {
    it('reports one event per field that failed, with the field name and never its message or value', async () => {
        const posthog = await started();

        trackFormErrors({
            email: 'El correo electrónico no es válido.',
            whatsapp: 'El WhatsApp debe tener 10 dígitos.',
        });

        expect(posthog.capture.mock.calls).toEqual([
            ['demo_form_error', { field: 'email' }],
            ['demo_form_error', { field: 'whatsapp' }],
        ]);
    });

    it('does nothing without errors', async () => {
        const posthog = await started();

        trackFormErrors({});

        expect(posthog.capture).not.toHaveBeenCalled();
    });
});
