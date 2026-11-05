import { describe, expect, it } from 'vite-plus/test';
import {
    addTermChange,
    removeTermChange,
    retroactiveLimit,
    raiseSummary,
    scenarioQuery,
    scenariosHeadline,
    setTermChange,
} from '@/lib/compensation/scenarios';
import type { TermChangeForm } from '@/lib/compensation/scenarios';

const change = (id: string, value = ''): TermChangeForm => ({
    benefit_id: id,
    name: `Prestación ${id}`,
    unit: 'pesos',
    value,
    current_text: null,
    tenure_table: [],
});

describe('scenarioQuery', () => {
    it('is empty for the active tab so a plain list has a plain address', () => {
        expect(scenarioQuery('active')).toEqual({});
    });

    it('carries the archived tab', () => {
        expect(scenarioQuery('archived')).toEqual({ status: 'archived' });
    });
});

describe('scenariosHeadline', () => {
    it('says there are none yet', () => {
        expect(scenariosHeadline({ active: 0, archived: 0 })).toBe(
            'Todavía no hay escenarios.',
        );
    });

    it('counts the active ones in the right number', () => {
        expect(scenariosHeadline({ active: 1, archived: 0 })).toBe(
            '1 escenario activo.',
        );
        expect(scenariosHeadline({ active: 3, archived: 0 })).toBe(
            '3 escenarios activos.',
        );
        expect(scenariosHeadline({ active: 0, archived: 2 })).toBe(
            '0 escenarios activos y 2 archivados.',
        );
    });

    it('adds the archived ones', () => {
        expect(scenariosHeadline({ active: 2, archived: 1 })).toBe(
            '2 escenarios activos y 1 archivado.',
        );
    });
});

describe('raiseSummary', () => {
    it('names the raise of each group', () => {
        expect(raiseSummary('5', '3')).toBe(
            '5 % sindicalizados · 3 % confianza',
        );
    });

    it('says the trusted staff copy the unionized raise', () => {
        expect(raiseSummary('5.75', null)).toBe(
            '5.75 % sindicalizados y confianza',
        );
    });
});

describe('retroactiveLimit', () => {
    it('is the months of the horizon', () => {
        expect(retroactiveLimit('3')).toBe(36);
        expect(retroactiveLimit('5')).toBe(60);
    });

    it('is the longest horizon while the horizon is not a number', () => {
        expect(retroactiveLimit('')).toBe(60);
        expect(retroactiveLimit('x')).toBe(60);
    });
});

describe('term changes', () => {
    const option = { value: 'b1', label: 'Bono', unit: 'pesos' };

    it('adds the benefit with an empty value', () => {
        expect(addTermChange([], option)).toEqual([
            {
                benefit_id: 'b1',
                name: 'Bono',
                unit: 'pesos',
                value: '',
                current_text: null,
                tenure_table: [],
            },
        ]);
    });

    it('does not add a benefit twice or the empty option', () => {
        const once = addTermChange([], option);

        expect(addTermChange(once, option)).toBe(once);
        expect(addTermChange(once, { ...option, value: '' })).toBe(once);
    });

    it('removes the change of a benefit', () => {
        expect(removeTermChange([change('a'), change('b')], 'a')).toEqual([
            change('b'),
        ]);
    });

    it('changes only the benefit it is asked to', () => {
        expect(
            setTermChange([change('a'), change('b')], 'b', { value: '12' }),
        ).toEqual([change('a'), change('b', '12')]);
    });
});
