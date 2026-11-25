import type { CaptureResult } from 'posthog-js';
import { describe, expect, it } from 'vitest';
import {
    posthogOptions,
    readAnalyticsConfig,
    sanitizeEvent,
    type AnalyticsConfig,
} from '@/lib/analytics';
import { HOST, TOKEN, documentWith } from '../support/analytics';

const CONFIG: AnalyticsConfig = {
    token: TOKEN,
    host: HOST,
    pageLocation: 'http://localhost/?utm_source=google',
};

function event(properties: CaptureResult['properties']): CaptureResult {
    return { uuid: 'u-1', event: '$pageview', properties };
}

describe('readAnalyticsConfig', () => {
    it('reads the token, the host and the page the server wrote', () => {
        const text = JSON.stringify({
            token: TOKEN,
            host: HOST,
            page_location: 'http://localhost/?utm_source=google',
        });

        expect(readAnalyticsConfig(documentWith(text))).toEqual(CONFIG);
    });

    it('reads the referrer when the server wrote one', () => {
        const text = JSON.stringify({
            token: TOKEN,
            host: HOST,
            page_location: 'http://localhost/',
            page_referrer: 'http://localhost/',
        });

        expect(readAnalyticsConfig(documentWith(text))).toEqual({
            token: TOKEN,
            host: HOST,
            pageLocation: 'http://localhost/',
            pageReferrer: 'http://localhost/',
        });
    });

    it('is null when the page has no block: no token or no consent on the server', () => {
        expect(readAnalyticsConfig(documentWith(null))).toBeNull();
    });

    it.each([
        ['empty text', ''],
        ['not JSON', '{nope'],
        ['a number', '7'],
        ['null', 'null'],
        ['an array', '[]'],
        ['no token', '{"host":"h","page_location":"p"}'],
        ['no host', '{"token":"t","page_location":"p"}'],
        ['no page', '{"token":"t","host":"h"}'],
        ['an empty token', '{"token":"","host":"h","page_location":"p"}'],
        [
            'a token that is not text',
            '{"token":1,"host":"h","page_location":"p"}',
        ],
        ['an empty host', '{"token":"t","host":"","page_location":"p"}'],
        ['an empty page', '{"token":"t","host":"h","page_location":""}'],
    ])('is null for a block that is %s', (_name, text) => {
        expect(readAnalyticsConfig(documentWith(text))).toBeNull();
    });

    it('is null when the block has no text', () => {
        const noText = { getElementById: () => ({ textContent: null }) };

        expect(readAnalyticsConfig(noText)).toBeNull();
    });

    it('ignores a referrer that is not text', () => {
        const text = JSON.stringify({
            token: TOKEN,
            host: HOST,
            page_location: 'http://localhost/',
            page_referrer: 5,
        });

        expect(readAnalyticsConfig(documentWith(text))).toEqual({
            token: TOKEN,
            host: HOST,
            pageLocation: 'http://localhost/',
        });
    });
});

describe('posthogOptions', () => {
    it('measures only the page view and its leave: no autocapture, no recordings, no surveys, no flags, no person profile', () => {
        const options = posthogOptions(CONFIG);

        expect(options).toMatchObject({
            api_host: HOST,
            autocapture: false,
            capture_pageview: false,
            capture_pageleave: true,
            disable_session_recording: true,
            disable_surveys: true,
            advanced_disable_flags: true,
            person_profiles: 'never',
            ip: false,
            persistence: 'localStorage',
            save_referrer: false,
            disable_external_dependency_loading: true,
        });
    });

    it('cleans every event before it leaves the browser', () => {
        const options = posthogOptions(CONFIG);
        const sent = [options.before_send].flat()[0]?.(
            event({ $current_url: 'http://localhost/?token=SECRET' }),
        );

        expect(sent?.properties['$current_url']).toBe(CONFIG.pageLocation);
    });
});

describe('sanitizeEvent', () => {
    it('replaces the page the browser knows with the one the server cleaned', () => {
        const sent = sanitizeEvent(CONFIG)(
            event({
                $current_url: 'http://localhost/?token=SECRET&email=a@b.mx',
                $pathname: '/',
            }),
        );

        expect(sent.properties['$current_url']).toBe(CONFIG.pageLocation);
        expect(sent.properties['$pathname']).toBe('/');
    });

    it('drops the referrer the browser read, and its domain', () => {
        const sent = sanitizeEvent(CONFIG)(
            event({
                $referrer: 'https://www.google.com/search?q=SECRET',
                $referring_domain: 'www.google.com',
            }),
        );

        expect(sent.properties).not.toHaveProperty('$referrer');
        expect(sent.properties).not.toHaveProperty('$referring_domain');
        expect(JSON.stringify(sent)).not.toContain('SECRET');
    });

    it('sends the referrer the server allowed: a page of this site, as an origin', () => {
        const sent = sanitizeEvent({
            ...CONFIG,
            pageReferrer: 'http://localhost/',
        })(event({ $referrer: 'http://localhost/survey/TOKEN' }));

        expect(sent.properties['$referrer']).toBe('http://localhost/');
        expect(JSON.stringify(sent)).not.toContain('TOKEN');
    });

    it('drops what would build a profile of the person: $set and $set_once', () => {
        const sent = sanitizeEvent(CONFIG)({
            ...event({}),
            $set: { email: 'a@b.mx' },
            $set_once: { $initial_referrer: 'https://x.example/?q=SECRET' },
        });

        expect(sent).not.toHaveProperty('$set');
        expect(sent).not.toHaveProperty('$set_once');
    });

    it('keeps the identity of the event: its id, its name and its time', () => {
        const timestamp = new Date('2026-11-15T10:00:00Z');
        const sent = sanitizeEvent(CONFIG)({
            uuid: 'u-9',
            event: 'generate_lead',
            properties: {},
            timestamp,
        });

        expect(sent.uuid).toBe('u-9');
        expect(sent.event).toBe('generate_lead');
        expect(sent.timestamp).toBe(timestamp);
    });

    it('does not invent a time the event did not have', () => {
        expect(sanitizeEvent(CONFIG)(event({}))).not.toHaveProperty(
            'timestamp',
        );
    });
});

describe('sanitizeEvent and what an error says', () => {
    function failure(value: string) {
        return {
            uuid: 'u-2',
            event: '$exception',
            properties: {
                $exception_list: [
                    {
                        type: 'Error',
                        value,
                        stacktrace: {
                            type: 'raw',
                            frames: [{ filename: 'app.js', lineno: 3 }],
                        },
                    },
                ],
            },
        };
    }

    function valueOf(result: CaptureResult): unknown {
        const list: unknown = result.properties['$exception_list'];

        return Array.isArray(list) ? Reflect.get(list[0], 'value') : undefined;
    }

    it.each([
        [
            'an email',
            'no se pudo enviar a ana@empresa.mx ya',
            'no se pudo enviar a [email] ya',
        ],
        ['a phone number', 'tel 55 1234 5678 fallo', 'tel [number] fallo'],
        ['a long number without spaces', 'id 5512345678', 'id [number]'],
        [
            'nothing personal',
            'Cannot read properties of undefined',
            'Cannot read properties of undefined',
        ],
    ])('masks %s in the message of an error', (_name, said, expected) => {
        const result = sanitizeEvent(CONFIG)(failure(said));

        expect(valueOf(result)).toBe(expected);
    });

    it('cuts a very long message', () => {
        const result = sanitizeEvent(CONFIG)(failure('x'.repeat(500)));

        expect(String(valueOf(result))).toHaveLength(200);
    });

    it('keeps the type and the frames of the error', () => {
        const result = sanitizeEvent(CONFIG)(failure('boom'));
        const list: unknown = result.properties['$exception_list'];

        expect(list).toEqual([
            {
                type: 'Error',
                value: 'boom',
                stacktrace: {
                    type: 'raw',
                    frames: [{ filename: 'app.js', lineno: 3 }],
                },
            },
        ]);
    });

    it('leaves an event that is not an error as it was', () => {
        const result = sanitizeEvent(CONFIG)(event({ section: 'tools' }));

        expect(result.properties['section']).toBe('tools');
        expect(result.properties['$exception_list']).toBeUndefined();
    });
});
