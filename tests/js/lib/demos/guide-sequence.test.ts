import { describe, expect, it } from 'vite-plus/test';

import { guideSequence } from '@/lib/demos/guide';

describe('guideSequence', () => {
    it('counts only the latest answer: a poll sent before a skip and landing after it cannot take the dock back', () => {
        const sequence = guideSequence();
        const poll = sequence.begin(false);
        const skip = sequence.begin(true);

        expect(sequence.settle(skip, true)).toBe(true);
        expect(sequence.settle(poll, false)).toBe(false);
    });

    it('counts no answer still on its way once a new page brings its guide', () => {
        const sequence = guideSequence();
        const poll = sequence.begin(false);
        sequence.supersede();

        expect(sequence.settle(poll, false)).toBe(false);
        expect(sequence.settle(sequence.begin(false), false)).toBe(true);
    });

    it('holds the poll while a skip is on its way, until every skip lands', () => {
        const sequence = guideSequence();
        expect(sequence.holdsPolls()).toBe(false);

        const first = sequence.begin(true);
        const second = sequence.begin(true);
        expect(sequence.holdsPolls()).toBe(true);

        sequence.settle(first, true);
        expect(sequence.holdsPolls()).toBe(true);

        // A superseded skip still lands: it no longer holds the poll.
        sequence.supersede();
        sequence.settle(second, true);
        expect(sequence.holdsPolls()).toBe(false);
    });

    it('never holds the poll for a poll', () => {
        const sequence = guideSequence();
        const poll = sequence.begin(false);

        expect(sequence.holdsPolls()).toBe(false);
        sequence.settle(poll, false);
        expect(sequence.holdsPolls()).toBe(false);
    });
});
