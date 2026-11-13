import { describe, expect, it } from 'vite-plus/test';
import { atLimit, expansionMailto, limitLine, planUsage } from '@/lib/limits';

describe('limitLine', () => {
    it('says how many places are left', () => {
        expect(limitLine({ used: 42, amount: 50, remaining: 8 })).toBe(
            'Quedan 8 lugares.',
        );
        expect(limitLine({ used: 49, amount: 50, remaining: 1 })).toBe(
            'Queda 1 lugar.',
        );
    });

    it('says there is no room at the limit', () => {
        expect(limitLine({ used: 50, amount: 50, remaining: 0 })).toBe(
            'Sin lugares: no puede agregar más hasta ampliar el plan.',
        );
    });

    it('says how many it has over when above the limit', () => {
        expect(limitLine({ used: 53, amount: 50, remaining: 0 })).toBe(
            'Tiene 3 lugares de más: conserva lo que tiene y no puede agregar.',
        );
        expect(limitLine({ used: 51, amount: 50, remaining: 0 })).toBe(
            'Tiene 1 lugar de más: conserva lo que tiene y no puede agregar.',
        );
    });
});

describe('planUsage', () => {
    const seats = { singular: 'persona', plural: 'personas' };

    it('says how much of the plan is used, discreetly, while there is room', () => {
        expect(
            planUsage(
                { used: 42, amount: 50, remaining: 8, over: false },
                seats,
            ),
        ).toEqual({ text: 'Usas 42 de 50 personas de tu plan.', full: false });
        expect(
            planUsage({ used: 1, amount: 1, remaining: 0, over: false }, seats),
        ).toEqual({
            text: 'Tu plan cubre 1 persona y ya llegaste al tope. Pide una ampliación a Humetría.',
            full: true,
        });
    });

    it('asks for an expansion at the limit and above it', () => {
        expect(
            planUsage(
                { used: 50, amount: 50, remaining: 0, over: false },
                seats,
            ),
        ).toEqual({
            text: 'Tu plan cubre 50 personas y ya llegaste al tope. Pide una ampliación a Humetría.',
            full: true,
        });
        expect(
            planUsage(
                { used: 53, amount: 50, remaining: 0, over: true },
                seats,
            ),
        ).toEqual({
            text: 'Tu plan cubre 50 personas y tienes 53. Pide una ampliación a Humetría.',
            full: true,
        });
    });
});

describe('atLimit', () => {
    const limit = { used: 3, amount: 5, remaining: 2, over: false };

    it('is false while there is room, and for a company without a plan', () => {
        expect(atLimit(limit)).toBe(false);
        expect(atLimit(null)).toBe(false);
    });

    it('is true at the limit and above it', () => {
        expect(atLimit({ ...limit, used: 5, remaining: 0 })).toBe(true);
        expect(atLimit({ used: 7, amount: 5, remaining: 0, over: true })).toBe(
            true,
        );
    });
});

describe('expansionMailto', () => {
    it('writes to the support address with the subject that says what to expand and for whom', () => {
        expect(
            expansionMailto(
                'soporte@humetria.mx',
                'centros',
                'Autopartes Demo',
            ),
        ).toBe(
            'mailto:soporte@humetria.mx?subject=Ampliar%20centros%20de%20Autopartes%20Demo',
        );
    });

    it('is null without an address, so no empty link is shown', () => {
        expect(expansionMailto(null, 'personas', 'Autopartes Demo')).toBeNull();
        expect(expansionMailto('', 'personas', 'Autopartes Demo')).toBeNull();
    });

    it('keeps accents and an ampersand of the company name safe in the link', () => {
        expect(expansionMailto('s@h.mx', 'personas', 'Peña & Hijos')).toBe(
            'mailto:s@h.mx?subject=Ampliar%20personas%20de%20Pe%C3%B1a%20%26%20Hijos',
        );
    });
});
