import { describe, expect, it } from 'vite-plus/test';
import {
    assumptionsSummary,
    compareBars,
} from '@/lib/compensation/scenario-draft';

describe('compareBars', () => {
    it('sorts the scenarios from the cheapest and sizes each against the dearest, this one marked', () => {
        const bars = compareBars(
            [
                {
                    id: 'a',
                    name: 'Revisión · 5 %',
                    first_year_cents: 200,
                    first_year_text: '$2',
                },
                {
                    id: 'b',
                    name: 'Revisión · 4 %',
                    first_year_cents: 100,
                    first_year_text: '$1',
                },
            ],
            { cents: 400, text: '$4' },
        );

        expect(bars).toEqual([
            {
                key: 'b',
                name: 'Revisión · 4 %',
                text: '$1',
                percent: 25,
                current: false,
            },
            {
                key: 'a',
                name: 'Revisión · 5 %',
                text: '$2',
                percent: 50,
                current: false,
            },
            {
                key: 'current',
                name: 'Este escenario',
                text: '$4',
                percent: 100,
                current: true,
            },
        ]);
    });

    it('draws no bar for a cost of zero or below and none without others', () => {
        expect(compareBars([], { cents: 0, text: '$0' })).toEqual([]);
        expect(
            compareBars(
                [
                    {
                        id: 'a',
                        name: 'A',
                        first_year_cents: -5,
                        first_year_text: '-$0',
                    },
                ],
                { cents: 0, text: '$0' },
            ).map((bar) => bar.percent),
        ).toEqual([0, 0]);
    });
});

describe('assumptionsSummary', () => {
    it('lists each assumption with its percent, the first one capitalised', () => {
        expect(
            assumptionsSummary([
                {
                    key: 'inflation',
                    label: 'Inflación',
                    value: '3',
                    source: '',
                    set_on: '',
                },
                {
                    key: 'turnover',
                    label: 'Rotación',
                    value: '0',
                    source: '',
                    set_on: '',
                },
            ]),
        ).toBe('Inflación 3 % · rotación 0 %');
    });
});
