import { describe, expect, it } from 'vitest';
import { accountsQuery, accountsSummary, figureHints } from '@/lib/billing';
import type { AccountSummary } from '@/types';

describe('accountsSummary', () => {
    it('says how many accounts there are, or that there are none', () => {
        expect(accountsSummary(0)).toBe('Aún no hay cuentas');
        expect(accountsSummary(1)).toBe('1 cuenta');
        expect(accountsSummary(12)).toBe('12 cuentas');
    });
});

describe('accountsQuery', () => {
    it('keeps nothing of the defaults in the address', () => {
        expect(accountsQuery({ q: '', kind: 'all' })).toEqual({});
        expect(accountsQuery({ q: '   ', kind: 'all' })).toEqual({});
    });

    it('carries the trimmed search and the kind that is not all', () => {
        expect(accountsQuery({ q: ' acme ', kind: 'consultant' })).toEqual({
            q: 'acme',
            kind: 'consultant',
        });
        expect(accountsQuery({ q: '', kind: 'direct' })).toEqual({
            kind: 'direct',
        });
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
