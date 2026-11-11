import { describe, expect, it } from 'vitest';
import { nextTab } from '@/lib/tabs';

describe('nextTab', () => {
    it('moves forward and back, wrapping at both ends', () => {
        expect(nextTab('ArrowRight', 0, 9)).toBe(1);
        expect(nextTab('ArrowDown', 8, 9)).toBe(0);
        expect(nextTab('ArrowLeft', 3, 9)).toBe(2);
        expect(nextTab('ArrowUp', 0, 9)).toBe(8);
    });

    it('jumps to the first and the last', () => {
        expect(nextTab('Home', 5, 9)).toBe(0);
        expect(nextTab('End', 2, 9)).toBe(8);
    });

    it('leaves any other key alone', () => {
        expect(nextTab('Enter', 2, 9)).toBeNull();
        expect(nextTab('a', 2, 9)).toBeNull();
    });
});
