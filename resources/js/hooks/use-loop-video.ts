import { useRef, useState } from 'react';

/**
 * A looping video and the state of its play/pause and sound buttons. It starts muted (autoplay needs it); the visitor
 * turns the narration on. The state follows the video itself, since autoplay can be refused.
 */
export function useLoopVideo() {
    const video = useRef<HTMLVideoElement>(null);
    const [playing, setPlaying] = useState(false);
    const [muted, setMuted] = useState(true);

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
        seek,
        playing,
        toggle,
        muted,
        toggleSound,
        onPlay: () => setPlaying(true),
        onPause: () => setPlaying(false),
    };
}
