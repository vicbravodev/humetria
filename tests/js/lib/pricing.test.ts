import { describe, expect, it } from 'vitest';
import {
    formatPesos,
    monthlyCost,
    parsePeople,
    SLIDER_MAX,
    SLIDER_MIN,
    sliderValue,
} from '@/lib/pricing';

describe('monthlyCost', () => {
    it('charges the rate per person once it passes the minimum', () => {
        expect(monthlyCost(39, 1990, 100)).toBe(3900);
        expect(monthlyCost(99, 4990, 100)).toBe(9900);
    });

    it('never charges less than the minimum of the plan', () => {
        expect(monthlyCost(39, 1990, 50)).toBe(1990);
        expect(monthlyCost(39, 1990, 1)).toBe(1990);
    });

    it('changes exactly where the rate overtakes the minimum', () => {
        expect(monthlyCost(39, 1990, 51)).toBe(1990);
        expect(monthlyCost(39, 1990, 52)).toBe(2028);
    });
});

describe('formatPesos', () => {
    it('writes a whole amount with the peso sign and thousands separators', () => {
        expect(formatPesos(1990)).toBe('$1,990');
        expect(formatPesos(19900)).toBe('$19,900');
        expect(formatPesos(39)).toBe('$39');
    });
});

describe('parsePeople', () => {
    it('reads a whole number of people', () => {
        expect(parsePeople('50')).toBe(50);
        expect(parsePeople(' 7 ')).toBe(7);
    });

    it('is null for what is not a count of people', () => {
        expect(parsePeople('')).toBeNull();
        expect(parsePeople('abc')).toBeNull();
        expect(parsePeople('0')).toBeNull();
        expect(parsePeople('-3')).toBeNull();
        expect(parsePeople('2.5')).toBeNull();
    });

    it('caps an absurd count so it still reads as "quote by hand"', () => {
        expect(parsePeople('99999999')).toBe(100000);
    });
});

describe('sliderValue', () => {
    it('keeps the slider inside its track, so a typed count never pushes the thumb out', () => {
        expect(sliderValue(50)).toBe(50);
        expect(sliderValue(1)).toBe(SLIDER_MIN);
        expect(sliderValue(5000)).toBe(SLIDER_MAX);
    });

    it('ends the track past the point where the catalog quotes by hand, so the last notch shows the quote', () => {
        expect(SLIDER_MAX).toBeGreaterThan(100);
        expect(sliderValue(SLIDER_MAX)).toBe(SLIDER_MAX);
    });
});
