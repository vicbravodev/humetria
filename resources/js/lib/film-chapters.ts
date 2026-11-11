/** Where a narrated film's chapters start, in seconds (video/voice/chapters.<id>.json), and how long the film is. */
export type FilmTiming = {
    readonly starts: readonly number[];
    readonly duration: number;
};

/** A chapter lasts as long as its voice line, so chapters are uneven and each film brings its own starts. */
export function chapterStart(timing: FilmTiming, index: number): number {
    return timing.starts[index] ?? 0;
}

/** The chapter the film is in at `time`: the last one from its start on (through the closing logo), the first before. */
export function activeChapter(timing: FilmTiming, time: number): number {
    const reached = timing.starts.filter((start) => start <= time).length;

    return Math.max(0, reached - 1);
}

/** How much of a chapter has played, from 0 to 1: the ones before the current are full, the ones after empty. The last runs to the end of the film. */
export function chapterProgress(
    timing: FilmTiming,
    index: number,
    time: number,
): number {
    const start = chapterStart(timing, index);
    const end = timing.starts[index + 1] ?? timing.duration;

    return Math.max(0, Math.min(1, (time - start) / (end - start)));
}
