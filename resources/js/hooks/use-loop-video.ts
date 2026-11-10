import { useRef, useState } from 'react';

/** A looping muted video and the state of its play/pause button: it follows the video itself, since autoplay can be refused. */
export function useLoopVideo() {
    const video = useRef<HTMLVideoElement>(null);
    const [playing, setPlaying] = useState(false);

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
        onPlay: () => setPlaying(true),
        onPause: () => setPlaying(false),
    };
}
