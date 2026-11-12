import { describe, expect, it } from 'vitest';
import {
    appliesTo,
    assignmentSentences,
    daysDelta,
    nameClash,
    premiumDelta,
    yearColumns,
} from '@/lib/leave/compare';

const options = [
    { value: 'conf', label: 'Personal de confianza' },
    { value: 'colec', label: 'Contrato colectivo' },
];

describe('daysDelta and premiumDelta', () => {
    it('says how much a figure improves the law, in the right number, and nothing when it only equals it', () => {
        expect(daysDelta(14, 12)).toBe('+2 días');
        expect(daysDelta(13, 12)).toBe('+1 día');
        expect(daysDelta(12, 12)).toBeNull();
        expect(premiumDelta(30, 25)).toBe('+5 pts');
        expect(premiumDelta(25, 25)).toBeNull();
    });

    it('never calls a figure under the law an improvement', () => {
        expect(daysDelta(10, 12)).toBeNull();
        expect(premiumDelta(20, 25)).toBeNull();
    });
});

describe('yearColumns', () => {
    const columns = yearColumns(
        [15, 22, 22, 22, 22, 23],
        [12, 14, 16, 18, 20, 22],
        25,
    );

    it('names the years as the law does: the sixth stands for 6 to 10', () => {
        expect(columns.map((column) => column.label)).toEqual([
            '1',
            '2',
            '3',
            '4',
            '5',
            '6–10',
        ]);
    });

    it('draws each year against the tallest of the comparison, from zero, with the law as the mark', () => {
        expect(columns[0]).toMatchObject({
            key: 'year-1',
            valueText: '15',
            percent: 60,
            referencePercent: 48,
        });
        expect(columns[5]?.percent).toBe(92);
    });

    it('says in words what each year is against the law', () => {
        expect(columns[0]?.tooltip).toBe('Año 1: 15 días; la ley da 12');
        expect(columns[5]?.tooltip).toBe('Años 6 a 10: 23 días; la ley da 22');
    });

    it('draws an empty chart for a whole of zero', () => {
        expect(yearColumns([12], [12], 0)[0]?.percent).toBe(0);
    });
});

describe('assignmentSentences', () => {
    it('says whom each policy goes to, or that the law follows', () => {
        expect(
            assignmentSentences(
                { default: 'conf', unionized: 'colec', trusted: null },
                options,
            ).map((line) => line.text),
        ).toEqual([
            'Toda la empresa sigue Personal de confianza',
            'Sindicalizados siguen Contrato colectivo',
            'Personal de confianza sigue la Ley (sin política)',
        ]);
    });

    it('says the law for a policy that is no longer offered', () => {
        expect(
            assignmentSentences(
                { default: 'archivada', unionized: null, trusted: null },
                options,
            )[0]?.text,
        ).toBe('Toda la empresa sigue la Ley (sin política)');
    });
});

describe('nameClash', () => {
    it('warns when a group follows the law and has the name of a policy', () => {
        expect(
            nameClash(
                { default: 'conf', unionized: 'colec', trusted: null },
                options,
            ),
        ).toBe('Personal de confianza');
    });

    it('says nothing when the group has its policy, or no policy has its name', () => {
        expect(
            nameClash(
                { default: null, unionized: null, trusted: 'conf' },
                options,
            ),
        ).toBeNull();
        expect(
            nameClash({ default: null, unionized: null, trusted: null }, [
                { value: 'x', label: 'Otra' },
            ]),
        ).toBeNull();
    });

    it('does not tell upper from lower case nor accents', () => {
        expect(
            nameClash({ default: null, unionized: null, trusted: null }, [
                { value: 'x', label: 'SINDICALIZADOS' },
            ]),
        ).toBe('Sindicalizados');
    });
});

describe('appliesTo', () => {
    it('lists the groups that follow a policy', () => {
        expect(
            appliesTo('conf', {
                default: 'conf',
                unionized: 'colec',
                trusted: 'conf',
            }),
        ).toEqual(['Toda la empresa', 'Personal de confianza']);
    });

    it('is empty for a policy nobody follows', () => {
        expect(
            appliesTo('x', { default: null, unionized: null, trusted: null }),
        ).toEqual([]);
    });
});
