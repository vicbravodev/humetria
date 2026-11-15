import { describe, expect, it } from 'vite-plus/test';
import {
    cascade,
    growDelay,
    niceCeiling,
    percentLabel,
    percentOf,
    pointsDelta,
} from '@/lib/chart';

describe('percentOf', () => {
    it('is the share of the largest, with one decimal', () => {
        expect(percentOf(551, 800)).toBe(68.9);
        expect(percentOf(800, 800)).toBe(100);
    });

    it('draws nothing for nothing, a negative value or no scale', () => {
        expect(percentOf(0, 800)).toBe(0);
        expect(percentOf(-5, 800)).toBe(0);
        expect(percentOf(5, 0)).toBe(0);
    });

    it('never passes the full bar', () => {
        expect(percentOf(900, 800)).toBe(100);
    });
});

describe('niceCeiling', () => {
    it('rounds up to the step, so the scale reads «de $0 a $800»', () => {
        expect(niceCeiling(561, 100)).toBe(600);
        expect(niceCeiling(700, 100)).toBe(700);
    });

    it('is one step at least', () => {
        expect(niceCeiling(0, 100)).toBe(100);
        expect(niceCeiling(-20, 100)).toBe(100);
    });
});

describe('cascade', () => {
    it('stacks each value on the ones before it and ends with the total, as fractions of the plot', () => {
        expect(cascade([60, 30, 10])).toEqual([
            { from: 0, to: 0.6 },
            { from: 0.6, to: 0.9 },
            { from: 0.9, to: 1 },
            { from: 0, to: 1 },
        ]);
    });

    it('draws a value that lowers the cost going down, inside a plot that takes the highest point', () => {
        expect(cascade([100, -20])).toEqual([
            { from: 0, to: 1 },
            { from: 0.8, to: 1 },
            { from: 0, to: 0.8 },
        ]);
    });

    it('keeps the axis inside the plot when the total goes below zero', () => {
        expect(cascade([10, -30])).toEqual([
            { from: 2 / 3, to: 1 },
            { from: 0, to: 1 },
            { from: 0, to: 2 / 3 },
        ]);
    });

    it('draws flat bars when there is nothing to stack', () => {
        expect(cascade([])).toEqual([{ from: 0, to: 0 }]);
        expect(cascade([0])).toEqual([
            { from: 0, to: 0 },
            { from: 0, to: 0 },
        ]);
    });
});

describe('growDelay', () => {
    it('staggers the bars 70 ms a row, and the late ones wait no more than the last step', () => {
        expect(growDelay(0)).toBe('[animation-delay:0ms]');
        expect(growDelay(2)).toBe('[animation-delay:140ms]');
        expect(growDelay(40)).toBe(growDelay(11));
    });
});

describe('percentLabel', () => {
    it('writes a whole percent with a space before the sign', () => {
        expect(percentLabel(18, 60)).toBe('30 %');
        expect(percentLabel(1, 3)).toBe('33 %');
        expect(percentLabel(0, 60)).toBe('0 %');
        expect(percentLabel(60, 60)).toBe('100 %');
    });

    it('is 0 % without a total', () => {
        expect(percentLabel(5, 0)).toBe('0 %');
    });
});

describe('pointsDelta', () => {
    it('signs a rise with plus and a fall with the typographic minus', () => {
        expect(pointsDelta(78, 62)).toBe('+16 pts');
        expect(pointsDelta(35, 62)).toBe('−27 pts');
    });

    it('says no change for the same value, rounded to whole points', () => {
        expect(pointsDelta(62, 62)).toBe('0 pts');
        expect(pointsDelta(62.4, 62)).toBe('0 pts');
    });
});
