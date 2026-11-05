import { describe, expect, it } from 'vitest';
import { advance, QUESTIONS } from '@/lib/telework/applies';

describe('advance', () => {
    it('asks how much of the time when someone works away from the center', () => {
        expect(advance('yes')).toEqual({ ask: 'share' });
    });

    it('declares that it does not apply when everyone works at the center', () => {
        expect(advance('no')).toEqual({ declare: false });
    });

    it('declares that it applies above 40 % and that it does not when it is now and then', () => {
        expect(advance('over')).toEqual({ declare: true });
        expect(advance('occasional')).toEqual({ declare: false });
    });

    it('waits while there is no answer, or the answer is "No lo sé"', () => {
        expect(advance(null)).toBeNull();
        expect(advance('unsure')).toBeNull();
        expect(advance(null)).toBeNull();
    });
});

describe('QUESTIONS', () => {
    it('asks the two questions of board V2-Aplica, in order', () => {
        expect(QUESTIONS.map((question) => question.key)).toEqual([
            'outside',
            'share',
        ]);
        expect(QUESTIONS[1]?.options.map((option) => option.label)).toEqual([
            'Sí, más del 40 %',
            'No, es de vez en cuando',
            'No lo sé',
        ]);
    });
});
