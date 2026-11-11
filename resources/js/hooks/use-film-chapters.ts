import { useState } from 'react';
import type { SyntheticEvent } from 'react';
import { useLoopVideo } from '@/hooks/use-loop-video';
import { chapterStart } from '@/lib/film-chapters';
import type { FilmTiming } from '@/lib/film-chapters';

/** A looping film that knows where it is, and can jump to a chapter and play from there. */
export function useFilmChapters(timing: FilmTiming) {
    const loop = useLoopVideo();
    const { seek } = loop;
    const [time, setTime] = useState(0);

    // Through the element's own event (not a listener added once) so a film swapped for its light version keeps being followed.
    const onTimeUpdate = (event: SyntheticEvent<HTMLVideoElement>): void =>
        setTime(event.currentTarget.currentTime);

    const jumpTo = (chapter: number): void => {
        setTime(chapterStart(timing, chapter));
        seek(chapterStart(timing, chapter));
    };

    return { ...loop, time, onTimeUpdate, jumpTo };
}
