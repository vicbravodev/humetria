import { describe, expect, it } from 'vitest';
import { accountsQuery, accountsSummary } from '@/lib/billing';

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
