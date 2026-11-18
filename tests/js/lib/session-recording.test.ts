import type { CaptureResult } from 'posthog-js';
import { describe, expect, it, vi } from 'vitest';
import {
    followPage,
    maskAttribute,
    readRecordingConfig,
    recordable,
    recordingOptions,
    replayUrl,
    snapshotOnly,
    startSessionRecording,
} from '@/lib/session-recording';
import { HOST, TOKEN } from '../support/analytics';

const ORIGIN = 'https://humetria.com.mx';

function pageWith(block: object | null) {
    return {
        getElementById: (id: string) =>
            id === 'error-tracking-config' && block !== null
                ? { textContent: JSON.stringify(block) }
                : null,
    };
}

const ON = pageWith({ token: TOKEN, host: HOST, recording: true });

/** An Inertia page of a company's panel, as the server hands it; each case changes one thing. */
function panel(component: string, props: object = {}) {
    return {
        component,
        url: '/admin/acme/org-chart',
        props: {
            auth: { user: { id: 7, name: 'Ana López' } },
            tenant: { slug: 'acme', name: 'Acme' },
            impersonator: null,
            ...props,
        },
    };
}

function recorder() {
    return {
        init: vi.fn(),
        startSessionRecording: vi.fn(),
        stopSessionRecording: vi.fn(),
    };
}

describe('readRecordingConfig', () => {
    it('reads the token and the host only when the server switched recording on', () => {
        expect(readRecordingConfig(ON)).toEqual({ token: TOKEN, host: HOST });
    });

    it.each([
        ['no block', pageWith(null)],
        ['recording off', pageWith({ token: TOKEN, host: HOST })],
        [
            'recording not true',
            pageWith({ token: TOKEN, host: HOST, recording: 'yes' }),
        ],
        ['no token', pageWith({ host: HOST, recording: true })],
    ])('is null with %s', (_name, source) => {
        expect(readRecordingConfig(source)).toBeNull();
    });
});

describe('recordable', () => {
    it('records a page of a company panel with somebody signed in', () => {
        expect(recordable(panel('organization/org-chart'))).toBe(true);
    });

    it.each([
        ['complaints', 'complaints/show'],
        ['the complaints channel', 'complaints/index'],
        ['Guía I by event', 'campaigns/trauma'],
        ['the Guía I list', 'results/screenings'],
        ['Humi', 'assistant/index'],
        ['the personal account', 'settings/security'],
    ])('never records %s', (_name, component) => {
        expect(recordable(panel(component))).toBe(false);
    });

    it.each([
        ['while impersonating', { impersonator: 'Soporte' }],
        ['outside a company (superadmin, sign in)', { tenant: null }],
        [
            'without anybody signed in (a survey, the portal)',
            { auth: { user: null } },
        ],
    ])('never records %s', (_name, props) => {
        expect(recordable(panel('organization/org-chart', props))).toBe(false);
    });

    it.each([null, 'a page', { component: 7 }, { component: 'x' }])(
        'is false for what is not a page: %s',
        (page) => {
            expect(recordable(page)).toBe(false);
        },
    );
});

describe('maskAttribute', () => {
    it.each([
        ['href', '/admin/acme/employees/42'],
        ['action', '/admin/acme/org-chart'],
        ['aria-label', 'Asignar jefe a Ana López'],
        ['title', 'Ana López'],
        ['alt', 'Foto de Ana López'],
        ['placeholder', 'ana@acme.mx'],
        ['value', 'Ana López'],
        ['data-name', 'Ana López'],
        ['src', 'https://cdn.example.com/avatar.png'],
    ])('masks %s', (name, value) => {
        expect(maskAttribute(name, value)).toBe('*');
    });

    it.each([
        ['class', 'flex gap-2'],
        ['style', 'width: 40px'],
        ['role', 'button'],
        ['type', 'button'],
        ['data-state', 'open'],
        ['aria-expanded', 'true'],
        ['viewBox', '0 0 24 24'],
        ['d', 'M3 3h18'],
        ['href', '/build/assets/app-1a2b.css'],
        ['src', '/images/tools/nom-035-01-campaigns-light.webp'],
    ])('keeps %s, which says how it looks and not who', (name, value) => {
        expect(maskAttribute(name, value)).toBe(value);
    });
});

describe('replayUrl', () => {
    it('names the page by its component, never by its address with the company and the ids', () => {
        expect(
            replayUrl(
                ORIGIN,
                () => 'organization/org-chart',
            )({
                name: 'https://humetria.com.mx/admin/acme/employees/42?q=ana',
            }),
        ).toEqual({ name: 'https://humetria.com.mx/organization/org-chart' });
    });

    it('names no page when it does not know which', () => {
        expect(replayUrl(ORIGIN, () => null)({ name: 'x' })).toEqual({
            name: 'https://humetria.com.mx/',
        });
    });
});

describe('snapshotOnly', () => {
    it('lets the recording through with only what PostHog needs to play it', () => {
        const event: CaptureResult = {
            uuid: 'u',
            event: '$snapshot',
            properties: {
                token: TOKEN,
                distinct_id: 'anon',
                $session_id: 's',
                $window_id: 'w',
                $snapshot_data: [{ type: 2 }],
                $snapshot_bytes: 10,
                $current_url: 'https://humetria.com.mx/admin/acme',
                $pathname: '/admin/acme',
                $referrer: 'https://humetria.com.mx/admin/acme/x',
                $initial_current_url: 'https://humetria.com.mx/admin/acme',
            },
        };

        expect(snapshotOnly(event)?.properties).toEqual({
            token: TOKEN,
            distinct_id: 'anon',
            $session_id: 's',
            $window_id: 'w',
            $snapshot_data: [{ type: 2 }],
            $snapshot_bytes: 10,
        });
    });

    it.each(['$pageview', '$autocapture', '$exception', '$identify'])(
        'drops %s: the recording instance sends nothing but the recording',
        (name) => {
            expect(
                snapshotOnly({ uuid: 'u', event: name, properties: {} }),
            ).toBeNull();
        },
    );

    it('drops nothing that is not there', () => {
        expect(snapshotOnly(null)).toBeNull();
    });
});

describe('recordingOptions', () => {
    const options = recordingOptions(
        { token: TOKEN, host: HOST },
        ORIGIN,
        () => null,
    );

    it('records anonymously: no profile, no address, nothing kept in the browser, no other capture', () => {
        expect(options).toMatchObject({
            api_host: HOST,
            person_profiles: 'never',
            persistence: 'memory',
            ip: false,
            autocapture: false,
            capture_pageview: false,
            capture_pageleave: false,
            capture_exceptions: false,
            capture_performance: false,
            capture_heatmaps: false,
            capture_dead_clicks: false,
            rageclick: false,
            disable_surveys: true,
            save_referrer: false,
            save_campaign_params: false,
            enable_recording_console_log: false,
            disable_external_dependency_loading: true,
            disable_session_recording: false,
        });
    });

    it('masks every text and input, records no network and blocks what is marked ph-no-capture', () => {
        expect(options.session_recording).toMatchObject({
            maskAllInputs: true,
            maskTextSelector: '*',
            blockClass: 'ph-no-capture',
            recordHeaders: false,
            recordBody: false,
            recordCrossOriginIframes: false,
            maskAttributeFn: maskAttribute,
        });
    });
});

describe('startSessionRecording and followPage', () => {
    it('downloads nothing while recording is off', async () => {
        const load = vi.fn();

        expect(
            await startSessionRecording(
                pageWith(null),
                panel('organization/org-chart'),
                { load: load, origin: ORIGIN },
            ),
        ).toBe(false);
        expect(load).not.toHaveBeenCalled();
    });

    it('waits for a page worth recording before it downloads the recorder', async () => {
        const posthog = recorder();
        const load = vi.fn(() => Promise.resolve(posthog));

        await startSessionRecording(ON, panel('settings/profile'), {
            load: load,
            origin: ORIGIN,
        });
        expect(load).not.toHaveBeenCalled();

        await followPage(panel('organization/org-chart'));
        expect(load).toHaveBeenCalledOnce();
        expect(posthog.init).toHaveBeenCalledWith(
            TOKEN,
            expect.objectContaining({ api_host: HOST }),
        );
    });

    it('stops on a page it must not record and starts again on the next one', async () => {
        const posthog = recorder();

        await startSessionRecording(ON, panel('organization/org-chart'), {
            load: () => Promise.resolve(posthog),
            origin: ORIGIN,
        });

        await followPage(panel('complaints/show'));
        expect(posthog.stopSessionRecording).toHaveBeenCalledOnce();

        await followPage(panel('organization/org-chart'));
        expect(posthog.startSessionRecording).toHaveBeenCalledOnce();
    });
});
