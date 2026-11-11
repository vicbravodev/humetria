import { describe, expect, it } from 'vite-plus/test';

import { arrowLabel, columnsOf } from '@/lib/engagement/trend';
import type { TrendPoint } from '@/types';

const point = (
    label: string,
    value: number,
    direction: TrendPoint['direction'] = null,
): TrendPoint => ({ surveyId: `id-${label}`, label, value, direction });

describe('arrowLabel', () => {
    it('names each direction and says nothing without one', () => {
        expect(arrowLabel('up')).toBe('Subió');
        expect(arrowLabel('down')).toBe('Bajó');
        expect(arrowLabel('same')).toBe('Igual');
        expect(arrowLabel(null)).toBe('');
    });
});

describe('columnsOf', () => {
    it('scales the tallest to 100 and the rest against it', () => {
        const columns = columnsOf(
            [
                point('oct 2026', 50),
                point('nov 2026', 100),
                point('dic 2026', 25),
            ],
            'percent',
        );

        expect(columns.map((column) => column.percent)).toEqual([50, 100, 25]);
        expect(columns.map((column) => column.valueText)).toEqual([
            '50 %',
            '100 %',
            '25 %',
        ]);
    });

    it('has the month and the value in the tooltip, and the arrow when there is one', () => {
        const [first, second] = columnsOf(
            [point('oct 2026', 60), point('nov 2026', 66, 'up')],
            'percent',
        );

        expect(first?.tooltip).toBe('oct 2026: 60 %');
        expect(second?.tooltip).toBe('nov 2026: 66 % · Subió');
        expect(second?.key).toBe('id-nov 2026');
        expect(second?.label).toBe('nov 2026');
    });

    it('leaves the columns flat when every value is zero', () => {
        expect(
            columnsOf([point('oct 2026', 0)], 'percent').map(
                (column) => column.percent,
            ),
        ).toEqual([0]);
    });

    it('puts the recommendation index, which goes from -100 to 100, with zero in the middle', () => {
        const columns = columnsOf(
            [
                point('oct 2026', -100),
                point('nov 2026', 0),
                point('dic 2026', 50),
            ],
            'index',
        );

        expect(columns.map((column) => column.percent)).toEqual([0, 50, 75]);
        expect(columns.map((column) => column.valueText)).toEqual([
            '-100',
            '0',
            '+50',
        ]);
    });

    it('has no columns without points', () => {
        expect(columnsOf([], 'percent')).toEqual([]);
    });
});
