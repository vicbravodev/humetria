import { describe, expect, it } from 'vite-plus/test';

import { cardClasses, dropLabel } from '@/lib/org-chart/card-style';

describe('cardClasses', () => {
    it('draws a normal card with the border of the design system', () => {
        expect(
            cardClasses({ tone: 'normal', attention: false, drop: null }),
        ).toContain('border-border');
    });

    it('outlines the selected card in the accent, with a soft shadow', () => {
        const classes = cardClasses({
            tone: 'selected',
            attention: false,
            drop: null,
        });

        expect(classes).toContain('border-2 border-primary');
        expect(classes).toContain('shadow-float');
    });

    it('lights the card on the way of a search, and dims the others', () => {
        expect(
            cardClasses({ tone: 'path', attention: false, drop: null }),
        ).toContain('bg-accent');
        expect(
            cardClasses({ tone: 'dimmed', attention: false, drop: null }),
        ).toContain('opacity-45');
    });

    it('fills in amber the card that asks for attention, unless it is selected', () => {
        expect(
            cardClasses({ tone: 'normal', attention: true, drop: null }),
        ).toContain('bg-warning');
        expect(
            cardClasses({ tone: 'selected', attention: true, drop: null }),
        ).toContain('border-primary');
        expect(
            cardClasses({ tone: 'dimmed', attention: true, drop: null }),
        ).toContain('opacity-45');
    });

    it('shows where a person can be dropped, and where not', () => {
        expect(
            cardClasses({ tone: 'normal', attention: false, drop: 'valid' }),
        ).toContain('border-dashed border-primary');
        expect(
            cardClasses({ tone: 'normal', attention: false, drop: 'invalid' }),
        ).toContain('cursor-not-allowed');
    });
});

describe('dropLabel', () => {
    it('tells the target what the drop will do, only when it can', () => {
        expect(dropLabel('valid', 'Alonso Banda')).toBe(
            'Soltar para que Alonso Banda sea su jefe',
        );
        expect(dropLabel('invalid', 'Alonso Banda')).toBe(
            'No se puede soltar aquí',
        );
        expect(dropLabel(null, 'Alonso Banda')).toBeNull();
    });
});
