import { describe, expect, it } from 'vitest';
import {
    CHAPTER_SECONDS,
    activeChapter,
    chapterProgress,
    chapterStart,
} from '@/lib/film-chapters';

describe('chapterStart', () => {
    it('starts each chapter every six seconds', () => {
        expect(CHAPTER_SECONDS).toBe(6);
        expect([0, 1, 2, 3].map(chapterStart)).toEqual([0, 6, 12, 18]);
    });
});

describe('activeChapter', () => {
    it('is the chapter the film is in', () => {
        expect(activeChapter(0, 4)).toBe(0);
        expect(activeChapter(5.99, 4)).toBe(0);
        expect(activeChapter(6, 4)).toBe(1);
        expect(activeChapter(17.9, 4)).toBe(2);
        expect(activeChapter(18, 4)).toBe(3);
    });

    it('stays on the last chapter at the very end and never goes below the first', () => {
        expect(activeChapter(24, 4)).toBe(3);
        expect(activeChapter(99, 4)).toBe(3);
        expect(activeChapter(-1, 4)).toBe(0);
    });
});

describe('chapterProgress', () => {
    it('fills the current chapter as it plays, with the past full and the future empty', () => {
        expect(chapterProgress(1, 9)).toBe(0.5);
        expect(chapterProgress(1, 6)).toBe(0);
        expect(chapterProgress(1, 12)).toBe(1);
        expect(chapterProgress(0, 20)).toBe(1);
        expect(chapterProgress(3, 6)).toBe(0);
    });
});
