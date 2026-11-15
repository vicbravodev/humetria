import { describe, expect, it } from 'vitest';
import {
    nextCut,
    parseTransferPreview,
    transferPriceChange,
} from '@/lib/billing-transfer';
import type { Quote } from '@/types';

const quote = (subtotal: string | null): Quote => ({
    requires_quotation: subtotal === null,
    explanation: 'Subtotal más 16% de IVA',
    lines: [],
    subtotal,
    subtotal_cents: subtotal === null ? null : 100,
    vat: subtotal === null ? null : '$1',
    total: subtotal === null ? null : '$2',
});

const effect = {
    role: 'from',
    account: 'Asesores',
    before: 3,
    after: 2,
    effect: 'loses',
    text: 'Asesores pierde el descuento',
};

const answer = (overrides: Record<string, unknown> = {}) => ({
    from: { id: 'a', name: 'Asesores' },
    to: { id: 'b', name: 'Directo' },
    effective_on: '1 ene 2027',
    current: quote('$2,490'),
    quote: quote('$3,990'),
    profile: { before: 'Canal', after: 'Directo' },
    effects: [effect],
    access: 'Sin cambios de acceso',
    ...overrides,
});

describe('parseTransferPreview', () => {
    it('reads a whole answer', () => {
        expect(parseTransferPreview(answer())).toEqual(answer());
    });

    it('reads a move to no payer and without a plan', () => {
        const parsed = parseTransferPreview(
            answer({
                to: null,
                current: null,
                quote: null,
                profile: null,
                effects: [],
            }),
        );

        expect(parsed?.to).toBeNull();
        expect(parsed?.quote).toBeNull();
        expect(parsed?.effects).toEqual([]);
    });

    it.each([
        ['not an object', 'x'],
        ['no origin', answer({ from: { id: 1 } })],
        ['a destination that is not one', answer({ to: 3 })],
        ['a quote that is not one', answer({ quote: { lines: 'x' } })],
        ['a profile that is not one', answer({ profile: { before: 1 } })],
        ['effects that are not a list', answer({ effects: 'x' })],
        [
            'an effect that is not one',
            answer({ effects: [{ ...effect, effect: 'maybe' }] }),
        ],
        [
            'an effect with a role that is not one',
            answer({ effects: [{ ...effect, role: 'x' }] }),
        ],
        ['an effect that is not an object', answer({ effects: [3] })],
        ['no date', answer({ effective_on: 1 })],
        ['no access note', answer({ access: null })],
    ])('refuses %s', (_name, value) => {
        expect(parseTransferPreview(value)).toBeNull();
    });
});

describe('transferPriceChange', () => {
    const preview = (current: string | null, next: string | null) => {
        const parsed = parseTransferPreview(
            answer({
                current: current === null ? null : quote(current),
                quote: next === null ? null : quote(next),
            }),
        );
        if (parsed === null) {
            throw new Error('not a preview');
        }
        return parsed;
    };

    it('writes the change in price', () => {
        expect(transferPriceChange(preview('$2,490', '$3,990'))).toBe(
            'De $2,490 a $3,990',
        );
    });

    it.each([
        ['the same price', '$2,490', '$2,490'],
        ['no plan', null, '$2,490'],
        ['no destination', '$2,490', null],
    ])('says nothing for %s', (_name, current, next) => {
        expect(transferPriceChange(preview(current, next))).toBeNull();
    });

    it('says nothing when a quotation is needed', () => {
        expect(
            transferPriceChange({
                ...preview('$2,490', '$3,990'),
                quote: quote(null),
            }),
        ).toBeNull();
    });
});

describe('nextCut', () => {
    it.each([
        ['2026-11-10', '2026-12-01'],
        ['2026-12-31', '2027-01-01'],
        ['2027-01-01', '2027-02-01'],
    ])('after %s comes %s', (today, cut) => {
        expect(nextCut(today)).toBe(cut);
    });
});
