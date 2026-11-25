import { describe, expect, it } from 'vitest';
import {
    moduleLabel,
    planPrice,
    planReach,
    plansQuery,
} from '@/lib/billing-catalog';
import type { CatalogPlan } from '@/types';

const plan = (overrides: Partial<CatalogPlan> = {}): CatalogPlan => ({
    key: 'cumplimiento',
    label: 'Cumplimiento',
    rate: '$49',
    flat: null,
    minimum: '$2,990',
    included_centers: 2,
    modules: ['nom035'],
    companies: 0,
    renewing: 0,
    ...overrides,
});

describe('moduleLabel', () => {
    it('names each module of the catalog as the company menu does', () => {
        expect(moduleLabel('org_chart')).toBe('Organigrama');
        expect(moduleLabel('nom035')).toBe('NOM-035');
        expect(moduleLabel('sso')).toBe('Inicio de sesión único');
    });

    it('shows the key of a module it does not know rather than nothing', () => {
        expect(moduleLabel('teleporting')).toBe('teleporting');
    });
});

describe('planPrice', () => {
    it('says the rate per person and its minimum, or the flat fee of a union', () => {
        expect(planPrice(plan())).toEqual({
            amount: '$49',
            per: 'por persona al mes',
            note: 'Mínimo $2,990 al mes',
        });
        expect(
            planPrice(plan({ rate: null, minimum: null, flat: '$1,990' })),
        ).toEqual({ amount: '$1,990', per: 'al mes', note: 'Cuota fija' });
    });
});

describe('planReach', () => {
    it('says how many companies are on the plan and how many renew in 90 days', () => {
        expect(planReach(plan())).toBe('Ninguna empresa');
        expect(planReach(plan({ companies: 1, renewing: 1 }))).toBe(
            '1 empresa · 1 renueva en 90 días',
        );
        expect(planReach(plan({ companies: 14, renewing: 3 }))).toBe(
            '14 empresas · 3 renuevan en 90 días',
        );
        expect(planReach(plan({ companies: 4, renewing: 0 }))).toBe(
            '4 empresas',
        );
    });
});

describe('plansQuery', () => {
    it('carries the trimmed search, and nothing when it is empty', () => {
        expect(plansQuery({ q: '  ' })).toEqual({});
        expect(plansQuery({ q: ' acme ' })).toEqual({ q: 'acme' });
    });
});
