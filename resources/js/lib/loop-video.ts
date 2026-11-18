/** What decides whether a looping film plays: where it is, what it is doing and what the visitor asked. */
export type LoopState = {
    /** The film is on screen, or about to be. */
    visible: boolean;
    paused: boolean;
    /** The visitor pressed pause: scrolling back to the film does not restart it. */
    pausedByVisitor: boolean;
    /** Reduced motion: no film starts by itself. */
    still: boolean;
};

export type LoopAction = 'play' | 'pause' | 'keep';

/**
 * A film plays only while it can be seen. Off screen it is paused (nothing decodes or loads for it) and it never loads for
 * a visitor who does not scroll to it; back in view it resumes unless the visitor paused it or prefers reduced motion.
 */
export function loopAction({
    visible,
    paused,
    pausedByVisitor,
    still,
}: LoopState): LoopAction {
    if (!visible) {
        return paused ? 'keep' : 'pause';
    }

    return paused && !pausedByVisitor && !still ? 'play' : 'keep';
}

/** What a browser may say about the connection (Chromium does; Safari and Firefox say nothing). */
export type NetworkHints = { saveData?: boolean; effectiveType?: string };

/** Reads the connection hints off a `navigator`, trusting only what has the right type. */
export function readNetworkHints(navigatorLike: object): NetworkHints {
    const connection: unknown = Reflect.get(navigatorLike, 'connection');

    if (typeof connection !== 'object' || connection === null) {
        return {};
    }

    const saveData: unknown = Reflect.get(connection, 'saveData');
    const effectiveType: unknown = Reflect.get(connection, 'effectiveType');

    return {
        ...(typeof saveData === 'boolean' ? { saveData } : {}),
        ...(typeof effectiveType === 'string' ? { effectiveType } : {}),
    };
}

const SLOW = new Set(['slow-2g', '2g', '3g']);

/**
 * Whether a film should wait on its poster for the visitor to press play: they asked to save data, or the connection is
 * too slow for megabytes of video. Without hints the film plays as always.
 */
export function filmWaitsForVisitor({
    saveData,
    effectiveType,
}: NetworkHints): boolean {
    return saveData === true || SLOW.has(effectiveType ?? '');
}
