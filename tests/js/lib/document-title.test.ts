import { describe, expect, it } from 'vitest';
import { documentTitle } from '@/lib/document-title';

describe('documentTitle', () => {
    it('puts the brand after the title of a page', () => {
        expect(documentTitle('Empleados', 'Humetría')).toBe(
            'Empleados - Humetría',
        );
    });

    it('keeps a title that already starts with the brand as the whole title', () => {
        expect(documentTitle('Humetría: NOM-035 y más', 'Humetría')).toBe(
            'Humetría: NOM-035 y más',
        );
    });

    it('is the brand alone when the page has no title', () => {
        expect(documentTitle('', 'Humetría')).toBe('Humetría');
    });

    it('does not take a title that only contains the brand for one that starts with it', () => {
        expect(documentTitle('Qué es Humetría', 'Humetría')).toBe(
            'Qué es Humetría - Humetría',
        );
    });
});
