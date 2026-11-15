import { describe, expect, it, vi } from 'vitest';
import {
    cleanProperties,
    clickEvent,
    createMilestones,
    errorSource,
    scrollPercent,
} from '@/lib/analytics-behavior';

function element(attributes: Record<string, string>) {
    return {
        closest: (selector: string) =>
            selector === '[data-track]'
                ? { getAttribute: (name: string) => attributes[name] ?? null }
                : null,
    };
}

describe('cleanProperties', () => {
    it('keeps short identifiers and numbers', () => {
        expect(
            cleanProperties({ target: 'hero_demo', percent: 50, tab: 'a-b.c' }),
        ).toEqual({ target: 'hero_demo', percent: 50, tab: 'a-b.c' });
    });

    it.each([
        ['an email', 'ana@empresa.mx'],
        ['free text with spaces', 'Ana Pérez'],
        ['an address', 'https://humetria.com.mx/?token=abc'],
        ['an empty value', ''],
        ['a value over 40 characters', 'a'.repeat(41)],
    ])(
        'drops %s: nothing about a person leaves the browser',
        (_name, value) => {
            expect(cleanProperties({ target: value, ok: 'yes' })).toEqual({
                ok: 'yes',
            });
        },
    );

    it('drops a number that is not finite', () => {
        expect(cleanProperties({ percent: Number.NaN })).toEqual({});
    });
});

describe('clickEvent', () => {
    it('reads the event and its target from the nearest tagged element', () => {
        expect(
            clickEvent(
                element({
                    'data-track': 'cta_click',
                    'data-track-target': 'hero_demo',
                }),
            ),
        ).toEqual({ event: 'cta_click', properties: { target: 'hero_demo' } });
    });

    it('reports an event without target as it is', () => {
        expect(clickEvent(element({ 'data-track': 'sign_in_click' }))).toEqual({
            event: 'sign_in_click',
            properties: {},
        });
    });

    it('does nothing for a click outside any tagged element', () => {
        expect(clickEvent(element({}))).toBeNull();
        expect(clickEvent({ closest: () => null })).toBeNull();
        expect(clickEvent(null)).toBeNull();
    });

    it.each(['Cta Click', '$pageview', 'x'.repeat(41), ''])(
        'refuses the event name %j: the name is a fixed word, not a free text',
        (name) => {
            expect(clickEvent(element({ 'data-track': name }))).toBeNull();
        },
    );

    it('drops a target that is not an identifier but still reports the click', () => {
        expect(
            clickEvent(
                element({
                    'data-track': 'cta_click',
                    'data-track-target': 'ana@x.mx',
                }),
            ),
        ).toEqual({ event: 'cta_click', properties: {} });
    });
});

describe('createMilestones', () => {
    it('reports each milestone once, in order, however far the visitor jumps', () => {
        const report = vi.fn();
        const reach = createMilestones([25, 50, 75, 100], report);

        reach(10);
        reach(26);
        reach(80);
        reach(30);
        reach(100);
        reach(100);

        expect(report.mock.calls).toEqual([[25], [50], [75], [100]]);
    });

    it('reports the exact limit', () => {
        const report = vi.fn();

        createMilestones([50], report)(50);

        expect(report).toHaveBeenCalledWith(50);
    });
});

describe('scrollPercent', () => {
    it('is how much of the page the bottom edge of the window has reached', () => {
        expect(
            scrollPercent({ scrollY: 500, innerHeight: 500, height: 2000 }),
        ).toBe(50);
    });

    it('never goes over 100', () => {
        expect(
            scrollPercent({ scrollY: 5000, innerHeight: 800, height: 2000 }),
        ).toBe(100);
    });

    it('is 100 for a page that fits in the window, and 0 for an empty one', () => {
        expect(
            scrollPercent({ scrollY: 0, innerHeight: 900, height: 600 }),
        ).toBe(100);
        expect(scrollPercent({ scrollY: 0, innerHeight: 900, height: 0 })).toBe(
            0,
        );
    });
});

describe('errorSource', () => {
    it('takes the error object of a window error', () => {
        const error = new Error('boom');

        expect(errorSource({ error, message: 'boom' })).toBe(error);
    });

    it('builds one from the message when the browser gives no object (a cross-origin script)', () => {
        const source = errorSource({ error: null, message: 'Script error.' });

        expect(source).toBeInstanceOf(Error);
        expect(source).toHaveProperty('message', 'Script error.');
    });

    it('takes the reason of a rejected promise, and wraps one that is not an error', () => {
        const error = new TypeError('x');

        expect(errorSource({ reason: error })).toBe(error);
        expect(errorSource({ reason: 'plain text' })).toBeInstanceOf(Error);
    });
});
