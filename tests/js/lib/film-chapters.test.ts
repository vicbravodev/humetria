import { describe, expect, it } from 'vitest';
import {
    activeChapter,
    chapterProgress,
    chapterStart,
} from '@/lib/film-chapters';

// A narrated film: each chapter lasts as long as its voice line, so the chapters are not the same length.
const timing = { starts: [0, 6.73, 13.77, 21.47], duration: 31.8 };

describe('chapterStart', () => {
    it('is the second each chapter starts, taken from the narration', () => {
        expect(
            [0, 1, 2, 3].map((index) => chapterStart(timing, index)),
        ).toEqual([0, 6.73, 13.77, 21.47]);
    });

    it('falls back to the start of the film for a chapter that does not exist', () => {
        expect(chapterStart(timing, 9)).toBe(0);
    });
});

describe('activeChapter', () => {
    it('is the chapter the film is in, with uneven chapters', () => {
        expect(activeChapter(timing, 0)).toBe(0);
        expect(activeChapter(timing, 6.72)).toBe(0);
        expect(activeChapter(timing, 6.73)).toBe(1);
        expect(activeChapter(timing, 13.76)).toBe(1);
        expect(activeChapter(timing, 21.47)).toBe(3);
    });

    it('stays on the last chapter through the closing logo and never goes below the first', () => {
        expect(activeChapter(timing, 31.8)).toBe(3);
        expect(activeChapter(timing, 99)).toBe(3);
        expect(activeChapter(timing, -1)).toBe(0);
    });
});

describe('chapterProgress', () => {
    it('fills the current chapter as it plays, with the past full and the future empty', () => {
        // Chapter 1 runs from 6.73 to 13.77 (7.04 s).
        expect(chapterProgress(timing, 1, 6.73)).toBe(0);
        expect(chapterProgress(timing, 1, 6.73 + 3.52)).toBeCloseTo(0.5, 5);
        expect(chapterProgress(timing, 1, 13.77)).toBe(1);
        expect(chapterProgress(timing, 0, 20)).toBe(1);
        expect(chapterProgress(timing, 3, 6)).toBe(0);
    });

    it('lets the last chapter run until the end of the film', () => {
        expect(
            chapterProgress(timing, 3, 21.47 + (31.8 - 21.47) / 2),
        ).toBeCloseTo(0.5, 5);
        expect(chapterProgress(timing, 3, 31.8)).toBe(1);
    });
});
