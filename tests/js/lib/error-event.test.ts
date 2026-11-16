import type { CaptureResult } from 'posthog-js';
import { describe, expect, it } from 'vitest';
import { errorOnlyEvent, pageContext, scrubUrl } from '@/lib/error-event';

const ORIGIN = 'https://humetria.com.mx';
const CONTEXT = { page: 'campaigns/survey', requestId: 'req-1' };

function exception(
    properties: CaptureResult['properties'],
    extra: Partial<CaptureResult> = {},
): CaptureResult {
    return { uuid: 'u-1', event: '$exception', properties, ...extra };
}

const send = errorOnlyEvent(ORIGIN, () => CONTEXT);

describe('scrubUrl', () => {
    it.each([
        [
            'a page with a one-use token',
            `${ORIGIN}/survey/SECRET?x=1#y`,
            `${ORIGIN}/`,
        ],
        [
            'a built asset',
            `${ORIGIN}/build/assets/app-1a2b.js?v=3`,
            `${ORIGIN}/build/assets/app-1a2b.js`,
        ],
        [
            'a source module of the dev server',
            'http://localhost:5173/resources/js/app.tsx',
            'http://localhost:5173/resources/js/app.tsx',
        ],
        ['an ES module', `${ORIGIN}/build/x.mjs`, `${ORIGIN}/build/x.mjs`],
        ['a typescript file', `${ORIGIN}/a/b.ts`, `${ORIGIN}/a/b.ts`],
        ['no url at all', '<anonymous>', '[url]'],
    ])('keeps of %s only what is not personal', (_name, url, expected) => {
        expect(scrubUrl(url)).toBe(expected);
    });
});

describe('pageContext', () => {
    it('reads the component and the request id of an Inertia page', () => {
        expect(
            pageContext({
                component: 'campaigns/survey',
                url: '/survey/SECRET',
                props: { requestId: 'req-1', token: 'SECRET' },
            }),
        ).toEqual(CONTEXT);
    });

    it.each([
        ['nothing', undefined],
        ['null', null],
        ['text', 'campaigns/survey'],
        ['no component nor props', {}],
        [
            'a component that is not text and props that are not an object',
            { component: 7, props: 'x' },
        ],
        ['props without a request id', { props: { requestId: 7 } }],
        ['null props', { props: null }],
    ])('is empty for %s', (_name, page) => {
        expect(pageContext(page)).toEqual({ page: null, requestId: null });
    });
});

describe('errorOnlyEvent', () => {
    it('lets nothing but an exception through', () => {
        expect(
            send(exception({ token: 't' }, { event: '$pageview' })),
        ).toBeNull();
        expect(send(exception({}, { event: 'generate_lead' }))).toBeNull();
    });

    it('tells the origin and the page name, never the address, and the request id', () => {
        const result = send(
            exception({
                $current_url: `${ORIGIN}/survey/SECRET`,
                $pathname: '/survey/SECRET',
                $host: 'humetria.com.mx',
                $referrer: `${ORIGIN}/engagement/SECRET`,
                $referring_domain: 'humetria.com.mx',
                $session_id: 's',
                $window_id: 'w',
                $exception_steps: [{ url: `${ORIGIN}/survey/SECRET` }],
                $exception_level: 'error',
                $lib: 'web',
                $browser: 'Chrome',
                token: 't',
                distinct_id: 'd',
            }),
        );

        expect(result?.properties).toEqual({
            $exception_level: 'error',
            $lib: 'web',
            $browser: 'Chrome',
            token: 't',
            distinct_id: 'd',
            $current_url: `${ORIGIN}/`,
            $process_person_profile: false,
            page: 'campaigns/survey',
            request_id: 'req-1',
        });
        expect(JSON.stringify(result)).not.toContain('SECRET');
    });

    it('builds no profile of the person and keeps the event identity and time', () => {
        const timestamp = new Date('2026-11-15T10:00:00Z');
        const result = send(
            exception(
                { token: 't' },
                {
                    $set: { email: 'a@b.mx' },
                    $set_once: { x: 1 },
                    $unset: ['y'],
                    timestamp,
                },
            ),
        );

        expect(result).toEqual({
            uuid: 'u-1',
            event: '$exception',
            timestamp,
            properties: expect.objectContaining({ token: 't' }),
        });
    });
});

describe('errorOnlyEvent: the exception list', () => {
    it('masks what the message may quote and keeps where it broke, without the page address', () => {
        const result = send(
            exception({
                $exception_list: [
                    {
                        type: 'TypeError',
                        value: `Failed for ana@x.mx at ${ORIGIN}/survey/SECRET, phone 55 1234 5678`,
                        stacktrace: {
                            type: 'raw',
                            frames: [
                                {
                                    filename: `${ORIGIN}/build/assets/app-1.js`,
                                    lineno: 3,
                                },
                                {
                                    filename: `${ORIGIN}/survey/SECRET`,
                                    abs_path: `${ORIGIN}/survey/SECRET`,
                                    lineno: 1,
                                },
                                'not a frame',
                            ],
                        },
                    },
                    { type: 'Error' },
                    'not an entry',
                ],
            }),
        );

        expect(result?.properties['$exception_list']).toEqual([
            {
                type: 'TypeError',
                value: `Failed for [email] at ${ORIGIN}/, phone [number]`,
                stacktrace: {
                    type: 'raw',
                    frames: [
                        {
                            filename: `${ORIGIN}/build/assets/app-1.js`,
                            lineno: 3,
                        },
                        {
                            filename: `${ORIGIN}/`,
                            abs_path: `${ORIGIN}/`,
                            lineno: 1,
                        },
                        'not a frame',
                    ],
                },
            },
            { type: 'Error' },
            'not an entry',
        ]);
    });

    it('leaves a stack without frames as it came', () => {
        const result = send(
            exception({
                $exception_list: [
                    { type: 'Error', stacktrace: { type: 'raw' } },
                    { type: 'Error', stacktrace: null },
                ],
            }),
        );

        expect(result?.properties['$exception_list']).toEqual([
            { type: 'Error', stacktrace: { type: 'raw' } },
            { type: 'Error', stacktrace: null },
        ]);
    });

    it('drops an exception list that is not a list, and tells no page before Inertia knows one', () => {
        const result = errorOnlyEvent(ORIGIN, () => ({
            page: null,
            requestId: null,
        }))(exception({ $exception_list: 'x' }));

        expect(result?.properties).toEqual({
            $current_url: `${ORIGIN}/`,
            $process_person_profile: false,
            page: null,
            request_id: null,
        });
    });
});
