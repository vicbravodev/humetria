import { describe, expect, it, vi } from 'vitest';
import {
    afterPageLoad,
    posthogOptions,
    readAnalyticsConfig,
    sanitizeEvent,
    startAnalytics,
    type AnalyticsConfig,
} from '@/lib/analytics';
import {
    HOST,
    LANDING_BLOCK,
    TOKEN,
    documentWith,
    engine,
} from '../support/analytics';

const CONFIG: AnalyticsConfig = {
    token: TOKEN,
    host: HOST,
    pageLocation: 'http://localhost/?utm_source=google',
};
const REPLAY: AnalyticsConfig = { ...CONFIG, replay: true };

describe('replay of the landing', () => {
    it('reads that the server switched replay on, and nothing else changes when it did not', () => {
        const text = (replay: boolean) =>
            JSON.stringify({
                token: TOKEN,
                host: HOST,
                page_location: 'http://localhost/',
                ...(replay ? { replay: true } : {}),
            });

        expect(readAnalyticsConfig(documentWith(text(true)))).toEqual({
            token: TOKEN,
            host: HOST,
            pageLocation: 'http://localhost/',
            replay: true,
        });
        expect(
            readAnalyticsConfig(documentWith(text(false))),
        ).not.toHaveProperty('replay');
    });

    it('records only masked and anonymous, and measures how fast the page loads, never what the network carries', () => {
        expect(posthogOptions(REPLAY)).toMatchObject({
            disable_session_recording: false,
            person_profiles: 'never',
            ip: false,
            disable_external_dependency_loading: true,
            enable_recording_console_log: false,
            capture_performance: { web_vitals: true, network_timing: false },
            session_recording: {
                maskAllInputs: true,
                blockClass: 'ph-no-capture',
                recordHeaders: false,
                recordBody: false,
                recordCrossOriginIframes: false,
            },
        });
    });

    it('keeps the replay off and the speed unmeasured without it', () => {
        const options = posthogOptions(CONFIG);

        expect(options.disable_session_recording).toBe(true);
        expect(options.capture_performance).toBeUndefined();
        expect(options.session_recording).toBeUndefined();
    });

    it('lets the frames go, still on the cleaned page', () => {
        const sent = sanitizeEvent(REPLAY)({
            uuid: 'u-2',
            event: '$snapshot',
            properties: {
                $snapshot_data: [{ type: 2 }],
                $session_id: 's-1',
                $current_url: 'http://localhost/?token=SECRET',
            },
        });

        expect(sent.properties['$snapshot_data']).toEqual([{ type: 2 }]);
        expect(sent.properties['$session_id']).toBe('s-1');
        expect(sent.properties['$current_url']).toBe(REPLAY.pageLocation);
    });

    it('asks for the full build (the recorder and the speed are inside) only when the server switched replay on', async () => {
        const load = vi.fn(() => Promise.resolve(engine()));
        const replay = JSON.stringify({
            ...JSON.parse(LANDING_BLOCK),
            replay: true,
        });

        await startAnalytics(documentWith(replay), load);
        await startAnalytics(documentWith(LANDING_BLOCK), load);

        expect(load.mock.calls).toEqual([[true], [false]]);
    });
});

describe('afterPageLoad', () => {
    it('does not wait when the page already finished loading', async () => {
        const listen = vi.fn();

        await afterPageLoad({
            document: { readyState: 'complete' },
            addEventListener: listen,
        });

        expect(listen).not.toHaveBeenCalled();
    });

    it('waits for the load event, so the heavier build never competes with what the visitor came to see', async () => {
        const listeners = new Map<string, () => void>();
        let done = false;

        const waiting = afterPageLoad({
            document: { readyState: 'loading' },
            addEventListener: (name: string, listener: () => void) =>
                listeners.set(name, listener),
        }).then(() => {
            done = true;
        });

        await Promise.resolve();
        expect(done).toBe(false);

        listeners.get('load')?.();
        await waiting;

        expect(done).toBe(true);
    });
});
