import { describe, expect, it } from 'vitest';
import {
    accountsQuery,
    ageOf,
    chargeAction,
    chosenOwed,
    collectedHint,
    figureHints,
    kindOf,
} from '@/lib/billing';
import type { AccountChargeRow, AccountFilters, AccountSummary } from '@/types';

const filters: AccountFilters = {
    q: '',
    kind: 'all',
    type: '',
    age: '',
    month: '',
};

describe('accountsQuery', () => {
    it('keeps nothing of the defaults in the address', () => {
        expect(accountsQuery(filters)).toEqual({});
        expect(accountsQuery({ ...filters, q: '   ' })).toEqual({});
    });

    it('carries the trimmed search and the kind that is not all', () => {
        expect(
            accountsQuery({ ...filters, q: ' acme ', kind: 'consultant' }),
        ).toEqual({
            q: 'acme',
            kind: 'consultant',
        });
        expect(accountsQuery({ ...filters, kind: 'direct' })).toEqual({
            kind: 'direct',
        });
    });

    it('carries the type, the age and the month chosen', () => {
        expect(
            accountsQuery({
                ...filters,
                type: 'consultant',
                age: '31_60',
                month: '2026-10',
            }),
        ).toEqual({ type: 'consultant', age: '31_60', month: '2026-10' });
    });
});

describe('kindOf and ageOf', () => {
    it('take only a kind or an age they know, and nothing else', () => {
        expect(kindOf('consultant')).toBe('consultant');
        expect(kindOf('direct')).toBe('direct');
        expect(kindOf('regalo')).toBe('');
        expect(ageOf('31_60')).toBe('31_60');
        expect(ageOf('current')).toBe('current');
        expect(ageOf('viejo')).toBe('');
    });
});

describe('collectedHint', () => {
    it('compares the month with the one before, signed', () => {
        expect(collectedHint(9, 'octubre')).toBe('+9 % contra octubre');
        expect(collectedHint(-12, 'octubre')).toBe('−12 % contra octubre');
        expect(collectedHint(0, 'octubre')).toBe('0 % contra octubre');
    });

    it('says only that payments were recorded when there is nothing to compare', () => {
        expect(collectedHint(null, 'octubre')).toBe('Pagos registrados');
    });
});

describe('chosenOwed', () => {
    it('adds up what the chosen accounts owe', () => {
        const rows = [
            { id: 'a', outstanding_cents: 1_280_000 },
            { id: 'b', outstanding_cents: 310_000 },
            { id: 'c', outstanding_cents: 0 },
        ];

        expect(chosenOwed(rows, ['a', 'b'])).toBe('$15,900');
        expect(chosenOwed(rows, [])).toBe('$0');
    });
});

describe('figureHints', () => {
    const summary = (
        overrides: Partial<AccountSummary> = {},
    ): AccountSummary => ({
        companies: 5,
        with_plan: 5,
        without_plan: 0,
        subtotal: '$19,950',
        vat: '$3,192',
        total: '$23,142',
        requiring_quotation: 0,
        next_renewal: '1 ene 2027',
        discount: null,
        ...overrides,
    });

    it('says all have a plan, and the VAT, when there is nothing missing', () => {
        expect(figureHints(summary())).toEqual({
            companies: 'Todas con plan',
            subtotal: undefined,
            total: '$3,192 de IVA',
        });
    });

    it('says how many lack a plan and how many need a quotation, and no VAT without a total', () => {
        expect(
            figureHints(
                summary({
                    without_plan: 2,
                    requiring_quotation: 1,
                    vat: null,
                    total: null,
                }),
            ),
        ).toEqual({
            companies: '2 sin plan asignado',
            subtotal: '1 empresa requiere cotización',
            total: undefined,
        });
        expect(figureHints(summary({ requiring_quotation: 3 })).subtotal).toBe(
            '3 empresas requieren cotización',
        );
    });
});

const charge: AccountChargeRow = {
    id: 'c1',
    company_id: 't1',
    trade_name: 'Acme',
    kind: 'recurring',
    kind_label: 'Recurrente',
    description: 'Acompañamiento',
    scope: 'Toda la empresa',
    amount: '$1,500',
    charge_on: '1 nov 2026',
    ends_on: null,
    approved_by: 'Victor',
    billed: false,
};

describe('chargeAction', () => {
    it('ends a recurring charge that has no last day', () => {
        expect(chargeAction(charge)?.kind).toBe('end');
        expect(chargeAction(charge)?.button).toBe('Terminar');
    });

    it('offers nothing for a recurring charge that already ends', () => {
        expect(chargeAction({ ...charge, ends_on: '31 dic 2026' })).toBeNull();
    });

    it('removes a one-time charge nobody billed, and offers nothing once it is billed', () => {
        const once: AccountChargeRow = { ...charge, kind: 'one_time' };

        expect(chargeAction(once)?.kind).toBe('remove');
        expect(chargeAction(once)?.submit).toBe('Quitar cargo');
        expect(chargeAction({ ...once, billed: true })).toBeNull();
    });
});
