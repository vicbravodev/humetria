import { useReducedMotion } from 'motion/react';
import FilmChapters from '@/components/landing/film-chapters';
import FilmControls from '@/components/landing/film-controls';
import type { ToolFilm } from '@/components/landing/landing-tools';
import { useFilmChapters } from '@/hooks/use-film-chapters';
import { useVideoFullscreen } from '@/hooks/use-video-fullscreen';
import { activeChapter } from '@/lib/film-chapters';

/** A framed screen with chapter navigation; reduced motion waits for an explicit play. */
export default function ToolFilmPlayer({ film }: { film: ToolFilm }) {
    const still = useReducedMotion() === true;
    const { video, playing, toggle, onPlay, onPause, time, jumpTo } =
        useFilmChapters();
    const { canFullscreen, fullscreen } = useVideoFullscreen(video);

    return (
        <figure
            data-test="tool-film"
            className="m-2 mb-0 overflow-hidden rounded-xl border border-input bg-muted shadow-raised sm:m-3 sm:mb-0"
        >
            <div className="relative">
                <video
                    ref={video}
                    autoPlay={!still}
                    muted
                    loop
                    playsInline
                    preload="metadata"
                    poster={`/video/${film.name}.jpg`}
                    onPlay={onPlay}
                    onPause={onPause}
                    className="block aspect-video w-full bg-muted object-contain"
                >
                    <source
                        src={`/video/${film.name}.webm`}
                        type="video/webm"
                    />
                    <source src={`/video/${film.name}.mp4`} type="video/mp4" />
                </video>
                <FilmControls
                    playing={playing}
                    onToggle={toggle}
                    canFullscreen={canFullscreen}
                    onFullscreen={fullscreen}
                />
            </div>
            <figcaption>
                <span className="sr-only">{film.description}</span>
                <FilmChapters
                    labels={film.chapters}
                    active={activeChapter(time, film.chapters.length)}
                    time={time}
                    onJump={jumpTo}
                />
            </figcaption>
        </figure>
    );
}
