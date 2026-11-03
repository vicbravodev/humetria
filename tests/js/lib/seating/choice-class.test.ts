import { describe, expect, it } from 'vitest';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';

describe('CHOICE_CLASS', () => {
    it('turns a checked choice to the accent and keeps its focus ring', () => {
        expect(CHOICE_CLASS).toContain('has-checked:border-primary');
        expect(CHOICE_CLASS).toContain('has-focus-visible:outline-ring');
    });
});
