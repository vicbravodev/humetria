import type { CaptureResult } from 'posthog-js';
import { describe, expect, it, vi } from 'vitest';
import { startAnalytics } from '@/lib/analytics';
import {
    errorContext,
    errorTrackingOptions,
    readErrorTrackingConfig,
    reportError,
    setErrorContext,
    startErrorTracking,
    watchErrors,
} from '@/lib/error-tracking';
import {
    HOST,
    LANDING_BLOCK,
    TOKEN,
    documentWith,
    engine,
} from '../support/analytics';

const ORIGIN = 'https://humetria.com.mx';
const BLOCK = JSON.stringify({ token: TOKEN, host: HOST });

function pageWith(text: string | null) {
    return {
        getElementById: (id: string) =>
            id === 'error-tracking-config' && text !== null
                ? { textContent: text }
                : null,
    };
}

async function started() {
    const posthog = engine();

    await startErrorTracking(
        pageWith(BLOCK),
        () => Promise.resolve(posthog),
        ORIGIN,
    );

    return posthog;
}

describe('readErrorTrackingConfig', () => {
    it('reads the token and the host the server wrote', () => {
        expect(readErrorTrackingConfig(pageWith(BLOCK))).toEqual({
            token: TOKEN,
            host: HOST,
        });
    });

    it.each([
        ['no block', null],
        ['not JSON', '{nope'],
        ['no token', JSON.stringify({ host: HOST })],
        ['no host', JSON.stringify({ token: TOKEN })],
    ])('is null with %s', (_name, text) => {
        expect(readErrorTrackingConfig(pageWith(text))).toBeNull();
    });
});

describe('errorTrackingOptions', () => {
    it('records nothing by itself, keeps nothing in the browser and builds no profile', () => {
        const options = errorTrackingOptions(
            { token: TOKEN, host: HOST },
            ORIGIN,
        );

        expect(options).toMatchObject({
            api_host: HOST,
            autocapture: false,
            capture_pageview: false,
            capture_pageleave: false,
            capture_exceptions: false,
            capture_performance: false,
            capture_heatmaps: false,
            capture_dead_clicks: false,
            rageclick: false,
            disable_session_recording: true,
            disable_surveys: true,
            advanced_disable_flags: true,
            person_profiles: 'never',
            ip: false,
            persistence: 'memory',
            save_referrer: false,
            save_campaign_params: false,
            disable_external_dependency_loading: true,
        });
    });

    it('sends only exceptions, with the page of the current context', () => {
        setErrorContext({
            component: 'dashboard',
            props: { requestId: 'req-9' },
        });
        const send = errorTrackingOptions(
            { token: TOKEN, host: HOST },
            ORIGIN,
        ).before_send;
        const event: CaptureResult = {
            uuid: 'u',
            event: '$exception',
            properties: {},
        };

        expect(
            typeof send === 'function' ? send(event)?.properties : null,
        ).toMatchObject({
            page: 'dashboard',
            request_id: 'req-9',
            $current_url: `${ORIGIN}/`,
        });
    });
});

describe('setErrorContext', () => {
    it('keeps the page and the request id of the last page Inertia showed', () => {
        setErrorContext({ component: 'a', props: { requestId: 'r-1' } });
        setErrorContext({ component: 'b', props: { requestId: 'r-2' } });

        expect(errorContext()).toEqual({ page: 'b', requestId: 'r-2' });
    });
});

describe('startErrorTracking', () => {
    it('starts PostHog with the error options when the page carries the block', async () => {
        const posthog = await started();

        expect(posthog.init).toHaveBeenCalledWith(
            TOKEN,
            expect.objectContaining({ api_host: HOST, persistence: 'memory' }),
        );
        expect(posthog.capture).not.toHaveBeenCalled();
    });

    it('does not even load PostHog without the block, and reports nothing then', async () => {
        await started();
        const load = vi.fn();

        expect(await startErrorTracking(pageWith(null), load, ORIGIN)).toBe(
            false,
        );
        expect(load).not.toHaveBeenCalled();
        expect(() => reportError(new Error('boom'))).not.toThrow();
    });
});

describe('reportError', () => {
    it('hands the error to PostHog once error tracking started', async () => {
        const posthog = await started();
        const error = new Error('boom');

        reportError(error);

        expect(posthog.captureException).toHaveBeenCalledWith(error);
    });

    it('goes to the landing measurement on the landing', async () => {
        await startErrorTracking(pageWith(null), vi.fn(), ORIGIN);
        const landing = engine();
        await startAnalytics(documentWith(LANDING_BLOCK), () =>
            Promise.resolve(landing),
        );
        const error = new Error('boom');

        reportError(error);

        expect(landing.captureException).toHaveBeenCalledWith(error);
    });
});

describe('watchErrors', () => {
    it('reports an uncaught error and a promise nobody caught, until it stops', () => {
        const target = new EventTarget();
        const report = vi.fn();
        const error = new Error('boom');
        const stop = watchErrors(target, report);

        target.dispatchEvent(Object.assign(new Event('error'), { error }));
        target.dispatchEvent(
            Object.assign(new Event('unhandledrejection'), { reason: error }),
        );
        stop();
        target.dispatchEvent(Object.assign(new Event('error'), { error }));

        expect(report).toHaveBeenCalledTimes(2);
        expect(report).toHaveBeenCalledWith(error);
    });

    it('makes an error of an event that only carries a message', () => {
        const target = new EventTarget();
        const report = vi.fn();
        watchErrors(target, report);

        target.dispatchEvent(
            Object.assign(new Event('error'), { message: 'Script error.' }),
        );

        expect(report).toHaveBeenCalledWith(new Error('Script error.'));
    });
});
