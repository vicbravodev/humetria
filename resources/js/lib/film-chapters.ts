/** Every tool film runs in chapters of the same length (video/hyperframes), so a chapter's moment is its index. */
export const CHAPTER_SECONDS = 6;

export function chapterStart(index: number): number {
    return index * CHAPTER_SECONDS;
}

/** The chapter the film is in at `time`: the last one from its end on, the first before its start. */
export function activeChapter(time: number, count: number): number {
    return Math.max(0, Math.min(count - 1, Math.floor(time / CHAPTER_SECONDS)));
}

/** How much of a chapter has played, from 0 to 1: the ones before the current are full, the ones after empty. */
export function chapterProgress(index: number, time: number): number {
    return Math.max(
        0,
        Math.min(1, (time - chapterStart(index)) / CHAPTER_SECONDS),
    );
}
