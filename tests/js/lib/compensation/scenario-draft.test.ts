import type { RaiseUnit } from '@/lib/compensation/scenario-draft';
import { describe, expect, it } from 'vite-plus/test';
import {
    headlineTexts,
    leftOutText,
    monthsLabel,
    parsePreview,
    parsePreviewErrors,
    signedYearly,
    stepMonths,
    stepRaise,
} from '@/lib/compensation/scenario-draft';
import {
    initialRaiseDraft,
    stepTrusted,
    trustedHint,
} from '@/lib/compensation/raise-draft';

const preview = {
    people: 90,
    groups: { unionized: 68, trusted: 22 },
    left_out: 1,
    first_year_cents: 123_965_100,
    first_year_text: '$1,239,651',
    retroactive_text: '$87,050',
    retroactive_month_text: '$87,050',
    recurring_text: '$1,152,601',
    per_person_month_text: '$1,067',
    over_today_text: '6.4',
    benefits: [{ benefit_id: 'b1', yearly_text: '$108,000' }],
};

describe('stepRaise', () => {
    it('moves a percent by half a point, never below zero nor over 100', () => {
        expect(stepRaise('6', 1, 'percent')).toBe('6.5');
        expect(stepRaise('6.5', -1, 'percent')).toBe('6');
        expect(stepRaise('0.25', -1, 'percent')).toBe('0');
        expect(stepRaise('99.8', 1, 'percent')).toBe('100');
    });

    it('moves an amount by 50 pesos, never below zero nor over 100,000', () => {
        expect(stepRaise('500', 1, 'amount')).toBe('550');
        expect(stepRaise('20', -1, 'amount')).toBe('0');
        expect(stepRaise('99990', 1, 'amount')).toBe('100000');
    });

    it('starts from zero what is not a number yet', () => {
        expect(stepRaise('', 1, 'percent')).toBe('0.5');
        expect(stepRaise('abc', -1, 'amount')).toBe('0');
    });
});

describe('stepMonths', () => {
    it('moves a month at a time between zero and the limit', () => {
        expect(stepMonths(1, 1, 36)).toBe(2);
        expect(stepMonths(0, -1, 36)).toBe(0);
        expect(stepMonths(36, 1, 36)).toBe(36);
    });
});

describe('monthsLabel', () => {
    it('says the retroactivity in words', () => {
        expect(monthsLabel(0)).toBe('Sin retroactivo');
        expect(monthsLabel(1)).toBe('1 mes');
        expect(monthsLabel(12)).toBe('12 meses');
    });
});

describe('parsePreview', () => {
    it('reads what the server costed', () => {
        expect(parsePreview(preview)).toEqual(preview);
    });

    it('refuses anything else', () => {
        expect(parsePreview(null)).toBeNull();
        expect(parsePreview({ ...preview, people: '90' })).toBeNull();
        expect(parsePreview({ ...preview, groups: null })).toBeNull();
        expect(
            parsePreview({ ...preview, benefits: [{ benefit_id: 1 }] }),
        ).toBeNull();
        expect(parsePreview({ ...preview, benefits: 'none' })).toBeNull();
    });
});

describe('parsePreviewErrors', () => {
    it('takes the first message of each field', () => {
        expect(
            parsePreviewErrors({
                message: 'x',
                errors: { unionized_raise: ['Escribe un porcentaje.', 'Otro'] },
            }),
        ).toEqual({ unionized_raise: 'Escribe un porcentaje.' });
    });

    it('leaves out a field that was sent empty: it is not missing until the form is saved', () => {
        const sent = new URLSearchParams({ value: '', name: 'Bono' });

        expect(
            parsePreviewErrors(
                { errors: { value: ['Obligatorio'], name: ['Repetido'] } },
                sent,
            ),
        ).toEqual({ name: 'Repetido' });
    });

    it('has none for an answer without errors', () => {
        expect(parsePreviewErrors({ message: 'x' })).toEqual({});
        expect(parsePreviewErrors('nope')).toEqual({});
        expect(parsePreviewErrors({ errors: { a: 'not a list' } })).toEqual({});
    });
});

describe('signedYearly', () => {
    it('signs what a benefit adds or saves a year', () => {
        expect(signedYearly('$108,000')).toBe('+$108,000 al año');
        expect(signedYearly('-$5,000')).toBe('-$5,000 al año');
    });
});

describe('leftOutText', () => {
    it('says who is left out of the cost', () => {
        expect(leftOutText(1)).toBe(
            '1 persona queda fuera porque no tiene categoría o salario.',
        );
        expect(leftOutText(3)).toBe(
            '3 personas quedan fuera porque no tienen categoría o salario.',
        );
    });
});

const amount: RaiseUnit = 'amount';
const percent: RaiseUnit = 'percent';

describe('initialRaiseDraft', () => {
    const defaults = {
        effective_on: '2027-01-01',
        horizon_years: 3,
        name: '',
        raise_unit: percent,
        unionized_raise: '0',
        trusted_raise: '',
        retroactive_months: 0,
    };
    const saved = {
        raise_unit: amount,
        unionized_raise: '608',
        trusted_raise: '',
        retroactive_months: 2,
        horizon_years: 4,
        effective_on: '2027-03-01',
    };

    it('starts a new scenario at no raise, the trusted copying the unionized', () => {
        expect(initialRaiseDraft(null, defaults)).toEqual({
            unit: 'percent',
            unionized: '0',
            trusted: '',
            months: 0,
            horizon: '3',
            effectiveOn: '2027-01-01',
        });
    });

    it('starts a new one at the point a link asks for', () => {
        expect(
            initialRaiseDraft(null, {
                ...defaults,
                raise_unit: amount,
                unionized_raise: '550',
                trusted_raise: '300',
                retroactive_months: 2,
            }),
        ).toEqual({
            unit: 'amount',
            unionized: '550',
            trusted: '300',
            months: 2,
            horizon: '3',
            effectiveOn: '2027-01-01',
        });
    });

    it('starts a saved one from its values', () => {
        expect(initialRaiseDraft(saved, defaults)).toEqual({
            unit: 'amount',
            unionized: '608',
            trusted: '',
            months: 2,
            horizon: '4',
            effectiveOn: '2027-03-01',
        });
        expect(
            initialRaiseDraft({ ...saved, trusted_raise: '300' }, defaults)
                .trusted,
        ).toBe('300');
    });
});

describe('the trusted raise', () => {
    const draft = initialRaiseDraft(null, {
        effective_on: '2027-01-01',
        horizon_years: 3,
        name: '',
        raise_unit: percent,
        unionized_raise: '0',
        trusted_raise: '',
        retroactive_months: 0,
    });

    it('steps from the unionized raise while it copies it, then from its own', () => {
        expect(stepTrusted({ ...draft, unionized: '6' }, 1)).toBe('6.5');
        expect(
            stepTrusted({ ...draft, unionized: '6', trusted: '3' }, -1),
        ).toBe('2.5');
    });

    it('says whether it copies, how many people and that it reaches individual salaries', () => {
        expect(trustedHint(draft, 22)).toBe(
            'Igual que sindicalizados · 22 personas · también a quien tiene sueldo individual',
        );
        expect(trustedHint({ ...draft, trusted: '3' }, undefined)).toBe(
            'también a quien tiene sueldo individual',
        );
    });
});

describe('headlineTexts', () => {
    it('shows a dash until the first answer, then its numbers', () => {
        expect(headlineTexts(null)).toEqual({
            first: '—',
            recurring: '—',
            perPerson: '—',
        });
        expect(headlineTexts(preview)).toEqual({
            first: '$1,239,651',
            recurring: '$1,152,601',
            perPerson: '$1,067',
        });
    });
});
