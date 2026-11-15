import { describe, expect, it } from 'vitest';
import {
    defaultPeriod,
    parseOpenStatements,
    parseStatementPreview,
    statementStateVariant,
} from '@/lib/billing-statements';

const preview = {
    period: 'dic 2026',
    lines: [{ label: 'Cumplimiento', amount: '$4,900', explanation: 'x' }],
    subtotal: '$4,900',
    vat: '$784',
    total: '$5,684',
};

describe('parseStatementPreview', () => {
    it('reads a whole answer', () => {
        expect(parseStatementPreview(preview)).toEqual(preview);
    });

    it.each([
        ['not an object', 'x'],
        ['no lines', { ...preview, lines: 'x' }],
        ['a line that is not one', { ...preview, lines: [1] }],
        [
            'a line missing its explanation',
            { ...preview, lines: [{ label: 'a', amount: 'b' }] },
        ],
        ['no period', { ...preview, period: 1 }],
        ['no subtotal', { ...preview, subtotal: null }],
        ['no VAT', { ...preview, vat: null }],
        ['no total', { ...preview, total: null }],
    ])('refuses %s', (_name, value) => {
        expect(parseStatementPreview(value)).toBeNull();
    });
});

describe('parseOpenStatements', () => {
    const row = {
        id: 'a',
        label: 'Uno · nov 2026',
        balance: '$3,980',
        balance_input: '3980',
    };

    it('reads the list', () => {
        expect(parseOpenStatements({ data: [row] })).toEqual([row]);
        expect(parseOpenStatements({ data: [] })).toEqual([]);
    });

    it.each([
        ['not an object', 3],
        ['no data', {}],
        ['a row that is not one', { data: ['x'] }],
        [
            'a row missing its balance',
            { data: [{ id: 'a', label: 'b', balance: 1, balance_input: 'c' }] },
        ],
    ])('refuses %s', (_name, value) => {
        expect(parseOpenStatements(value)).toBeNull();
    });
});

describe('statementStateVariant', () => {
    it('gives every state a badge', () => {
        expect(statementStateVariant('draft')).toBe('outline');
        expect(statementStateVariant('paid')).toBe('default');
    });
});

describe('defaultPeriod', () => {
    it('is the month of today', () => {
        expect(defaultPeriod('2026-11-10')).toBe('2026-11');
    });
});
