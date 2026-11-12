import { describe, expect, it } from 'vite-plus/test';

import {
    bandOf,
    changeLabel,
    changeSinceFirst,
    changeSincePrevious,
    columnsOf,
    signedColumnsOf,
    sortByChange,
    trendReading,
} from '@/lib/engagement/trend';
import type { TrendPoint, TrendSeries } from '@/types';

const point = (
    label: string,
    value: number,
    direction: TrendPoint['direction'] = null,
): TrendPoint => ({ surveyId: `id-${label}`, label, value, direction });

const series = (key: string, points: TrendPoint[]): TrendSeries => ({
    key,
    title: key,
    hint: null,
    kind: 'percent',
    points,
});

describe('bandOf', () => {
    it('calls two points or less either way no change, and past that up or down', () => {
        expect(bandOf(2)).toBe('same');
        expect(bandOf(-2)).toBe('same');
        expect(bandOf(0)).toBe('same');
        expect(bandOf(3)).toBe('up');
        expect(bandOf(-3)).toBe('down');
    });
});

describe('changeSinceFirst', () => {
    it('measures the latest against the first measurement', () => {
        expect(
            changeSinceFirst([
                point('jun 2026', 62),
                point('jul 2026', 69, 'up'),
                point('ago 2026', 72, 'up'),
                point('sep 2026', 71, 'down'),
            ]),
        ).toEqual({ points: 9, band: 'up', from: 'jun 2026' });
    });

    it('has nothing to say with a single measurement', () => {
        expect(changeSinceFirst([point('jun 2026', 62)])).toBeNull();
        expect(changeSinceFirst([])).toBeNull();
    });

    it('does not compare when the first or the latest wave were too small for an arrow', () => {
        expect(
            changeSinceFirst([
                point('jun 2026', 62),
                point('jul 2026', 69, null),
                point('ago 2026', 72, 'up'),
            ]),
        ).toBeNull();
        expect(
            changeSinceFirst([
                point('jun 2026', 62),
                point('jul 2026', 69, 'up'),
                point('ago 2026', 72, null),
            ]),
        ).toBeNull();
    });
});

describe('changeSincePrevious', () => {
    it('measures the latest against the one before, inside the band of no change', () => {
        expect(
            changeSincePrevious([
                point('ago 2026', 72),
                point('sep 2026', 71, 'down'),
            ]),
        ).toEqual({ points: -1, band: 'same', from: 'ago 2026' });
    });

    it('has nothing without an arrow or a previous wave', () => {
        expect(changeSincePrevious([point('sep 2026', 71)])).toBeNull();
        expect(
            changeSincePrevious([point('ago 2026', 72), point('sep 2026', 50)]),
        ).toBeNull();
    });
});

describe('changeLabel', () => {
    it('says the points and which way, or that it did not change', () => {
        expect(changeLabel({ points: 12, band: 'up', from: 'x' })).toBe(
            'Subió 12 pts',
        );
        expect(changeLabel({ points: -5, band: 'down', from: 'x' })).toBe(
            'Bajó 5 pts',
        );
        expect(changeLabel({ points: -1, band: 'same', from: 'x' })).toBe(
            'Sin cambio',
        );
        expect(changeLabel({ points: 3, band: 'up', from: 'x' })).toBe(
            'Subió 3 pts',
        );
    });
});

describe('columnsOf', () => {
    it('draws every share from zero on a scale of 0 to 100', () => {
        const columns = columnsOf(
            [point('jun 2026', 62), point('jul 2026', 69)],
            'percent',
        );

        expect(columns.map((column) => column.percent)).toEqual([62, 69]);
        expect(columns.map((column) => column.valueText)).toEqual([
            '62 %',
            '69 %',
        ]);
    });

    it('keeps a share inside the scale', () => {
        expect(
            columnsOf([point('a', -4), point('b', 140)], 'percent').map(
                (column) => column.percent,
            ),
        ).toEqual([0, 100]);
    });

    it('says in the tooltip how it moved against the wave before, with the band of no change', () => {
        const [first, second, third] = columnsOf(
            [
                point('jul 2026', 69),
                point('ago 2026', 72, 'up'),
                point('sep 2026', 71, 'down'),
            ],
            'percent',
        );

        expect(first?.tooltip).toBe('jul 2026: 69 %');
        expect(second?.tooltip).toBe('ago 2026: 72 % · Subió 3 pts');
        expect(third?.tooltip).toBe('sep 2026: 71 % · Sin cambio');
        expect(third?.key).toBe('id-sep 2026');
        expect(third?.label).toBe('sep 2026');
    });

    it('writes the recommendation index with its sign and a real minus', () => {
        expect(
            columnsOf(
                [point('a', -27), point('b', 0), point('c', 13)],
                'index',
            ).map((column) => column.valueText),
        ).toEqual(['−27', '0', '+13']);
    });

    it('has no columns without points', () => {
        expect(columnsOf([], 'percent')).toEqual([]);
    });
});

describe('signedColumnsOf', () => {
    it('hangs negative values down from zero on an axis rounded to fifty', () => {
        const chart = signedColumnsOf([point('jun', -27), point('sep', -13)]);

        expect(chart.min).toBe(-50);
        expect(chart.max).toBe(0);
        expect(chart.zero).toBe(0);
        expect(
            chart.columns.map((column) => [column.top, column.height]),
        ).toEqual([
            [0, 54],
            [0, 26],
        ]);
        expect(chart.columns[0]?.valueText).toBe('−27');
    });

    it('puts zero inside an axis that crosses it', () => {
        const chart = signedColumnsOf([point('a', -20), point('b', 60)]);

        expect([chart.min, chart.max]).toEqual([-50, 100]);
        expect(chart.zero).toBe(66.7);
        expect(
            chart.columns.map((column) => [column.top, column.height]),
        ).toEqual([
            [66.7, 13.3],
            [26.7, 40],
        ]);
    });

    it('keeps an axis for no points', () => {
        expect(signedColumnsOf([])).toEqual({
            min: 0,
            max: 50,
            zero: 100,
            columns: [],
        });
    });
});

describe('sortByChange', () => {
    it('puts what rose most first and what cannot be compared last', () => {
        const sorted = sortByChange([
            series('down', [point('a', 68), point('b', 63, 'down')]),
            series('none', [point('a', 50)]),
            series('up', [point('a', 72), point('b', 84, 'up')]),
        ]);

        expect(sorted.map((one) => one.key)).toEqual(['up', 'down', 'none']);
    });
});

describe('trendReading', () => {
    it('says how the engagement moved since the first wave and where the recommendation went', () => {
        expect(
            trendReading(
                [
                    point('jun 2026', 62),
                    point('ago 2026', 72, 'up'),
                    point('sep 2026', 71, 'down'),
                ],
                [point('jun 2026', -27), point('sep 2026', -13, 'up')],
            ),
        ).toBe(
            'El compromiso subió 9 puntos desde jun 2026 y se mantiene. La recomendación mejora: de −27 a −13.',
        );
    });

    it('says when the engagement fell or held, and leaves out a recommendation that did not move', () => {
        expect(
            trendReading(
                [point('jun 2026', 70), point('sep 2026', 60, 'down')],
                [point('jun 2026', -10), point('sep 2026', -9, 'up')],
            ),
        ).toBe('El compromiso bajó 10 puntos desde jun 2026.');
        expect(
            trendReading(
                [point('jun 2026', 70), point('sep 2026', 71, 'up')],
                [point('jun 2026', 10), point('sep 2026', 4, 'down')],
            ),
        ).toBe(
            'El compromiso se mantiene desde jun 2026. La recomendación baja: de +10 a +4.',
        );
    });

    it('has nothing to read with a single wave', () => {
        expect(trendReading([point('jun 2026', 62)], [])).toBeNull();
    });
});
