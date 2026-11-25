import { describe, expect, it } from 'vitest';
import { applyBrandAccent, brandCssOf } from '@/lib/brand-accent';

type Style = {
    id: string;
    textContent: string | null;
    nonce: string;
    remove(): void;
};

/** A page with a head: just enough document for the accent's <style>. */
function fakeDocument(existing?: string) {
    const head: Style[] = [];
    const make = (css: string): Style => {
        const style: Style = {
            id: 'brand-accent',
            textContent: css,
            nonce: '',
            remove: () => head.splice(head.indexOf(style), 1),
        };

        return style;
    };

    if (existing !== undefined) {
        head.push(make(existing));
    }

    return {
        head,
        document: {
            getElementById: (id: string) =>
                head.find((style) => style.id === id) ?? null,
            createElement: (): Style => make(''),
            head: { append: (style: Style) => head.push(style) },
        },
    };
}

describe('applyBrandAccent', () => {
    it("puts the company's accent on a page that had none, with the page's nonce", () => {
        const { head, document } = fakeDocument();

        applyBrandAccent(document, ':root{--primary:#cd1a57;}', 'abc');

        expect(head).toHaveLength(1);
        expect(head[0]?.textContent).toBe(':root{--primary:#cd1a57;}');
        expect(head[0]?.nonce).toBe('abc');
    });

    it('swaps the accent for the one of the company the visit arrives in, never stacking two', () => {
        const { head, document } = fakeDocument(':root{--primary:#cd1a57;}');

        applyBrandAccent(document, ':root{--primary:#1565c0;}', undefined);

        expect(head).toHaveLength(1);
        expect(head[0]?.textContent).toBe(':root{--primary:#1565c0;}');
    });

    it("takes the accent off when the visit leaves the company's pages (the landing after a logout, Humetría's own panel)", () => {
        const { head, document } = fakeDocument(':root{--primary:#cd1a57;}');

        applyBrandAccent(document, null, undefined);

        expect(head).toHaveLength(0);
    });

    it('does nothing for a page with no accent and none on it', () => {
        const { head, document } = fakeDocument();

        applyBrandAccent(document, null, undefined);

        expect(head).toHaveLength(0);
    });
});

describe('brandCssOf', () => {
    it("reads the accent the server shared with the page, or null when there is none or the page is not a company's", () => {
        expect(brandCssOf({ brand: { css: ':root{}' } })).toBe(':root{}');
        expect(brandCssOf({ brand: { css: null } })).toBeNull();
        expect(brandCssOf({ brand: null })).toBeNull();
        expect(brandCssOf({})).toBeNull();
        expect(brandCssOf(undefined)).toBeNull();
        expect(brandCssOf({ brand: { css: 3 } })).toBeNull();
    });
});
