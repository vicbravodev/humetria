import { describe, expect, it } from 'vitest';
import {
    parseSubscriptionPreview,
    priceChange,
    saveLabel,
} from '@/lib/billing-preview';
import type { Quote, SubscriptionPreview } from '@/types';

const quote = (subtotal: string | null): Quote => ({
    requires_quotation: subtotal === null,
    explanation: 'Subtotal más 16% de IVA',
    lines:
        subtotal === null
            ? []
            : [{ label: 'Cumplimiento', amount: subtotal, explanation: 'x' }],
    subtotal,
    subtotal_cents: subtotal === null ? null : 100,
    vat: subtotal === null ? null : '$1',
    total: subtotal === null ? null : '$2',
});

const answer = (overrides: Record<string, unknown> = {}) => ({
    quote: quote('$3,990'),
    current: quote('$2,490'),
    first_period: null,
    eligibility: null,
    scheduled: null,
    modules: { added: [], removed: [], removedOn: null },
    ...overrides,
});

describe('parseSubscriptionPreview', () => {
    it('reads a whole answer', () => {
        expect(parseSubscriptionPreview(answer())).toEqual(answer());
    });

    it('reads one with a quotation, a first period and a note', () => {
        const parsed = parseSubscriptionPreview(
            answer({
                quote: quote(null),
                current: null,
                first_period: quote('$1,000'),
                eligibility: { applies: true, reason: 'Porque sí' },
            }),
        );

        expect(parsed?.quote.requires_quotation).toBe(true);
        expect(parsed?.current).toBeNull();
        expect(parsed?.first_period?.subtotal).toBe('$1,000');
        expect(parsed?.eligibility).toEqual({
            applies: true,
            reason: 'Porque sí',
        });
    });

    it('reads the day a reduction waits for', () => {
        const parsed = parseSubscriptionPreview(
            answer({ scheduled: { effective_on: '1 dic 2026' } }),
        );

        expect(parsed?.scheduled).toEqual({ effective_on: '1 dic 2026' });
    });
});

describe('parseSubscriptionPreview, modules and refusals', () => {
    it('reads the tools a plan adds and takes away', () => {
        const modules = {
            added: ['Pulso'],
            removed: ['Vacaciones', 'Organigramas'],
            removedOn: '10 dic 2026',
        };

        expect(parseSubscriptionPreview(answer({ modules }))?.modules).toEqual(
            modules,
        );
    });

    it.each([
        ['modules missing', answer({ modules: undefined })],
        ['modules that are not an object', answer({ modules: 'x' })],
        [
            'removed that is not a list of text',
            answer({
                modules: { added: [], removed: [1], removedOn: null },
            }),
        ],
        [
            'added that is not a list',
            answer({
                modules: { added: 'x', removed: [], removedOn: null },
            }),
        ],
        [
            'a day that is not text',
            answer({ modules: { added: [], removed: [], removedOn: 5 } }),
        ],
    ])('refuses %s', (_name, value) => {
        expect(parseSubscriptionPreview(value)).toBeNull();
    });

    it.each([
        ['not an object', 'hola'],
        ['null', null],
        ['no quote', answer({ quote: undefined })],
        ['a quote that is not one', answer({ quote: 'x' })],
        [
            'a quote without lines',
            answer({ quote: { ...quote('$1'), lines: 'x' } }),
        ],
        [
            'a line that is not one',
            answer({ quote: { ...quote('$1'), lines: [{ label: 1 }] } }),
        ],
        [
            'a line that is not an object',
            answer({ quote: { ...quote('$1'), lines: ['x'] } }),
        ],
        [
            'a quote with a flag that is not boolean',
            answer({ quote: { ...quote('$1'), requires_quotation: 'no' } }),
        ],
        [
            'a quote with an explanation that is not text',
            answer({ quote: { ...quote('$1'), explanation: 1 } }),
        ],
        [
            'a quote with a subtotal that is not text',
            answer({ quote: { ...quote('$1'), subtotal: 1 } }),
        ],
        [
            'a quote with a vat that is not text',
            answer({ quote: { ...quote('$1'), vat: 1 } }),
        ],
        [
            'a quote with a total that is not text',
            answer({ quote: { ...quote('$1'), total: 1 } }),
        ],
        [
            'a quote with cents that are not a number',
            answer({ quote: { ...quote('$1'), subtotal_cents: 'x' } }),
        ],
        ['a current that is not a quote', answer({ current: 'x' })],
        ['a first period that is not a quote', answer({ first_period: 'x' })],
        ['a note that is not one', answer({ eligibility: { applies: 'sí' } })],
        [
            'a schedule that is not one',
            answer({ scheduled: { effective_on: 1 } }),
        ],
    ])('refuses %s', (_name, value) => {
        expect(parseSubscriptionPreview(value)).toBeNull();
    });
});

describe('priceChange', () => {
    const preview = (current: string | null, next: string | null) =>
        ({
            quote: quote(next),
            current: current === null ? null : quote(current),
            first_period: null,
            eligibility: null,
            scheduled: null,
            modules: { added: [], removed: [], removedOn: null },
        }) satisfies SubscriptionPreview;

    it('says from what to what when the price moves', () => {
        expect(priceChange(preview('$2,490', '$3,990'))).toBe(
            'De $2,490 a $3,990',
        );
    });

    it('says nothing when it stays, when there is no current price or when a quotation is needed', () => {
        expect(priceChange(preview('$2,490', '$2,490'))).toBeNull();
        expect(priceChange(preview(null, '$3,990'))).toBeNull();
        expect(priceChange(preview('$2,490', null))).toBeNull();
    });
});

describe('saveLabel', () => {
    const none = { added: [], removed: [], removedOn: null };

    it('says nothing special when no tool goes away', () => {
        expect(saveLabel(none)).toBe('Guardar plan');
        expect(saveLabel(null)).toBe('Guardar plan');
    });

    it('counts the tools the plan removes', () => {
        expect(
            saveLabel({ ...none, removed: ['A'], removedOn: '1 ene 2027' }),
        ).toBe('Asignar y quitar 1 herramienta');
        expect(
            saveLabel({ ...none, removed: ['A', 'B'], removedOn: 'x' }),
        ).toBe('Asignar y quitar 2 herramientas');
    });
});
