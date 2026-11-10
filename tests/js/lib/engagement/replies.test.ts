import { describe, expect, it } from 'vite-plus/test';
import {
    SKIPPED,
    closedOf,
    resumeIndex,
    tokenOfPath,
} from '@/lib/engagement/replies';
import type { Step } from '@/lib/engagement/survey-flow';

const list: Step[] = [
    { kind: 'intro' },
    {
        kind: 'question',
        section: 0,
        item: { key: 'a', text: 'A', scale: 'agreement' },
    },
    {
        kind: 'question',
        section: 0,
        item: { key: 'b', text: 'B', scale: 'agreement' },
    },
    {
        kind: 'question',
        section: 0,
        item: { key: 'c', text: 'C', scale: 'open' },
    },
    { kind: 'submit' },
];

describe('closedOf', () => {
    it('turns the stored digits into numbers and leaves out the skipped ones', () => {
        expect(closedOf({ a: '4', b: SKIPPED, c: '0', d: '10' })).toEqual({
            a: 4,
            c: 0,
            d: 10,
        });
    });

    it('ignores anything that is not a whole number', () => {
        expect(closedOf({ a: '4.5', b: '-1', c: 'x', d: '' })).toEqual({});
    });
});

describe('resumeIndex', () => {
    it('opens at the introduction when nothing was answered', () => {
        expect(resumeIndex(list, {})).toBe(0);
    });

    it('opens at the first closed question without an answer or a skip', () => {
        expect(resumeIndex(list, { a: '3' })).toBe(2);
    });

    it('opens right after the last closed question seen when none is left', () => {
        expect(resumeIndex(list, { a: SKIPPED, b: '2' })).toBe(3);
    });

    it('opens at the send step when the last step was a closed question', () => {
        const closedOnly = list.filter(
            (step) => step.kind !== 'question' || step.item.scale !== 'open',
        );

        expect(resumeIndex(closedOnly, { a: '1', b: '2' })).toBe(3);
    });
});

describe('tokenOfPath', () => {
    it('reads the token of the link', () => {
        expect(tokenOfPath('/engagement/abc123')).toBe('abc123');
        expect(tokenOfPath('/engagement/a%20b')).toBe('a b');
    });

    it('gives null for any other address', () => {
        expect(tokenOfPath('/survey/abc')).toBeNull();
        expect(tokenOfPath('/engagement/abc/more')).toBeNull();
    });
});
