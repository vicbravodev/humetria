import { useCallback, useRef, useState } from 'react';

/**
 * A looping video and the state of its play/pause and sound buttons. It starts muted (autoplay needs it); the visitor
 * turns the narration on. The state follows the video itself, since autoplay can be refused.
 */
export function useLoopVideo() {
    const video = useRef<HTMLVideoElement>(null);
    const [playing, setPlaying] = useState(false);
    const [muted, setMuted] = useState(true);

    /**
     * The video's ref. React only sets the `muted` property, and axe's no-autoplay-audio rule reads the attribute: without
     * it axe waits for a narrated film to play and times out, so the attribute is set here as the element mounts.
     */
    const watcher = useRef<IntersectionObserver | null>(null);

    /** The narration stays with what the visitor is looking at: once less than half the film is on screen it is muted. */
    const attach = useCallback((element: HTMLVideoElement | null): void => {
        watcher.current?.disconnect();
        video.current = element;

        if (element === null) {
            return;
        }

        element.setAttribute('muted', '');
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
    }, []);

    const toggle = (): void => {
        const element = video.current;

        if (element === null) {
            return;
        }

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
