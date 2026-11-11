import { useEffect, useState } from 'react';
import { useLoopVideo } from '@/hooks/use-loop-video';
import { chapterStart } from '@/lib/film-chapters';
import type { FilmTiming } from '@/lib/film-chapters';

/** A looping film that knows where it is, and can jump to a chapter and play from there. */
export function useFilmChapters(timing: FilmTiming) {
    const loop = useLoopVideo();
    const { video, seek } = loop;
    const [time, setTime] = useState(0);

    useEffect(() => {
        const element = video.current;

        if (element === null) {
            return;
        }

        const follow = (): void => setTime(element.currentTime);

        element.addEventListener('timeupdate', follow);

        return () => element.removeEventListener('timeupdate', follow);
    }, [video]);

    const jumpTo = (chapter: number): void => {
        setTime(chapterStart(timing, chapter));
        seek(chapterStart(timing, chapter));
    };

    return { ...loop, time, jumpTo };
}
