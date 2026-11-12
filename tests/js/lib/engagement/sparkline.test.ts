import { describe, expect, it } from 'vite-plus/test';

import { sparklineOf, wavesSpan } from '@/lib/engagement/sparkline';
import type { TrendPoint } from '@/types';

const waves = [
    { surveyId: 'a', label: 'jun 2026' },
    { surveyId: 'b', label: 'jul 2026' },
    { surveyId: 'c', label: 'ago 2026' },
    { surveyId: 'd', label: 'sep 2026' },
];

const point = (surveyId: string, value: number): TrendPoint => ({
    surveyId,
    label: surveyId,
    value,
    direction: null,
});

describe('wavesSpan', () => {
    it('counts the waves and says from which month to which', () => {
        expect(wavesSpan(waves)).toBe('4 mediciones · jun 2026 a sep 2026');
    });

    it('names a single wave by itself, and nothing for none', () => {
        expect(wavesSpan(waves.slice(0, 1))).toBe('1 medición · jun 2026');
        expect(wavesSpan([])).toBeNull();
    });
});

describe('sparklineOf', () => {
    it('places each point at its wave on the common scale of 0 to 100', () => {
        const line = sparklineOf([point('a', 50), point('d', 100)], waves, {
            width: 120,
            height: 32,
        });

        expect(line?.path).toBe('M0 16L120 0');
        expect(line?.dot).toEqual({ x: 120, y: 0 });
        expect(line?.baseline).toBe(16);
        expect(line?.tail).toBeNull();
    });

    it('draws a dashed tail to the latest wave when the theme was not asked in it', () => {
        const line = sparklineOf([point('a', 0), point('c', 100)], waves, {
            width: 120,
            height: 32,
        });

        expect(line?.path).toBe('M0 32L80 0');
        expect(line?.tail).toBe('M80 0L120 0');
    });

    it('has nothing to draw without points, and a point alone on one wave', () => {
        expect(sparklineOf([], waves, { width: 120, height: 32 })).toBeNull();
        expect(
            sparklineOf([point('a', 50)], waves.slice(0, 1), {
                width: 120,
                height: 32,
            })?.path,
        ).toBe('M0 16');
    });
});
