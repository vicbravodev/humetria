import { useReducedMotion } from 'motion/react';
import { useAppearance } from '@/hooks/use-appearance';
import { show as showFilm } from '@/routes/films';
import FilmChapters from '@/components/landing/film-chapters';
import FilmControls from '@/components/landing/film-controls';
import type { ToolFilm } from '@/components/landing/landing-tools';
import { useFilmChapters } from '@/hooks/use-film-chapters';
import { useVideoFullscreen } from '@/hooks/use-video-fullscreen';
import { activeChapter } from '@/lib/film-chapters';

/** A framed screen with chapter navigation; reduced motion waits for an explicit play. */
export default function ToolFilmPlayer({ film }: { film: ToolFilm }) {
    const still = useReducedMotion() === true;
    const {
        video,
        attach,
        playing,
        toggle,
        muted,
        toggleSound,
        onPlay,
        onPause,
        time,
        onTimeUpdate,
        jumpTo,
    } = useFilmChapters(film.timing);
    const { canFullscreen, fullscreen } = useVideoFullscreen(video);
    // The film is drawn with the design tokens of each theme, so it sits in the page instead of being a dark block in it.
    const { resolvedAppearance } = useAppearance();
    const name =
        resolvedAppearance === 'light' ? `${film.name}-light` : film.name;

    return (
        <figure
            data-test="tool-film"
            className="overflow-hidden rounded-2xl border border-input bg-muted"
        >
            <div className="relative">
                <video
                    key={name}
                    ref={attach}
                    autoPlay={!still}
                    muted
                    loop
                    playsInline
                    preload="metadata"
                    poster={`/video/${name}.jpg`}
                    onTimeUpdate={onTimeUpdate}
                    onPlay={onPlay}
                    onPause={onPause}
                    className="block aspect-video w-full bg-muted object-contain"
                >
                    <source
                        src={showFilm.url({ film: `${name}.webm` })}
                        type="video/webm"
                    />
                    <source
                        src={showFilm.url({ film: `${name}.mp4` })}
                        type="video/mp4"
                    />
                </video>
                <FilmControls
                    playing={playing}
                    onToggle={toggle}
                    muted={muted}
                    onToggleSound={toggleSound}
                    canFullscreen={canFullscreen}
                    onFullscreen={fullscreen}
                />
            </div>
            <figcaption className="bg-card sm:pr-40">
                <span className="sr-only">{film.description}</span>
                <FilmChapters
                    labels={film.chapters}
                    timing={film.timing}
                    active={activeChapter(film.timing, time)}
                    time={time}
                    onJump={jumpTo}
                />
            </figcaption>
        </figure>
    );
}
