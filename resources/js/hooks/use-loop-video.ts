import { useCallback, useEffect, useRef, useState } from 'react';
import {
    filmWaitsForVisitor,
    loopAction,
    readNetworkHints,
} from '@/lib/loop-video';

/** How far past the edge of the screen a film is already counted as in view, so it has started when it arrives. */
const NEAR = '150px 0px';

/**
 * A looping video and the state of its play/pause and sound buttons. It starts muted (autoplay needs it); the visitor
 * turns the narration on. The state follows the video itself, since autoplay can be refused. The film plays only while it
 * is in view (lib/loop-video.ts): it is not downloaded for a visitor who never scrolls to it and it stops when it leaves.
 * With `still` (reduced motion), Save-Data or a slow connection it never starts by itself.
 */
export function useLoopVideo(still = false) {
    const video = useRef<HTMLVideoElement>(null);
    const stillNow = useRef(still);
    const slowLine = useRef(false);
    const pausedByVisitor = useRef(false);
    const [playing, setPlaying] = useState(false);
    const [muted, setMuted] = useState(true);

    /**
     * The video's ref. React only sets the `muted` property, and axe's no-autoplay-audio rule reads the attribute: without
     * it axe waits for a narrated film to play and times out, so the attribute is set here as the element mounts.
     */
    const watcher = useRef<IntersectionObserver | null>(null);
    const player = useRef<IntersectionObserver | null>(null);

    useEffect(() => {
        stillNow.current = still;
    }, [still]);

    /** The narration stays with what the visitor is looking at: once less than half the film is on screen it is muted. */
    const attach = useCallback((element: HTMLVideoElement | null): void => {
        watcher.current?.disconnect();
        player.current?.disconnect();
        video.current = element;

        if (element === null) {
            return;
        }

        element.setAttribute('muted', '');
        // Save-Data or a slow connection: the film stays on its poster until the visitor presses play.
        slowLine.current = filmWaitsForVisitor(readNetworkHints(navigator));
        watcher.current = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting === false) {
                    element.muted = true;
                    setMuted(true);
                }
            },
            { threshold: 0.5 },
        );
        watcher.current.observe(element);
        player.current = new IntersectionObserver(
            ([entry]) => {
                const action = loopAction({
                    visible: entry?.isIntersecting === true,
                    paused: element.paused,
                    pausedByVisitor: pausedByVisitor.current,
                    still: stillNow.current || slowLine.current,
                });

                if (action === 'play') {
                    element.play().catch(() => setPlaying(false));
                } else if (action === 'pause') {
                    element.pause();
                }
            },
            { rootMargin: NEAR },
        );
        player.current.observe(element);
    }, []);

    const toggle = (): void => {
        const element = video.current;

        if (element === null) {
            return;
        }

        pausedByVisitor.current = !element.paused;

        if (element.paused) {
            element.play().catch(() => setPlaying(false));
        } else {
            element.pause();
        }
    };

    const toggleSound = (): void => {
        const element = video.current;

        if (element === null) {
            return;
        }

        element.muted = !element.muted;
        setMuted(element.muted);
    };

    /** Moves the film to `second` and plays from there. */
    const seek = (second: number): void => {
        const element = video.current;

        if (element === null) {
            return;
        }

        pausedByVisitor.current = false;
        element.currentTime = second;
        element.play().catch(() => undefined);
    };

    return {
        video,
        attach,
        seek,
        playing,
        toggle,
        muted,
        toggleSound,
        onPlay: () => setPlaying(true),
        onPause: () => setPlaying(false),
    };
}
