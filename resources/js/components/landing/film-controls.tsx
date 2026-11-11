import { Maximize, Pause, Play, Volume2, VolumeX } from 'lucide-react';

type Props = {
    playing: boolean;
    muted: boolean;
    onToggleSound: () => void;
    canFullscreen: boolean;
    onToggle: () => void;
    onFullscreen: () => void;
};

const button =
    'flex size-11 shrink-0 items-center justify-center rounded-lg border border-input bg-card text-foreground shadow-raised transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none';

export default function FilmControls({
    playing,
    muted,
    onToggleSound,
    canFullscreen,
    onToggle,
    onFullscreen,
}: Props) {
    return (
        <div className="absolute right-2 bottom-2 z-1 flex items-center gap-1.5 sm:-bottom-12 sm:h-12 sm:pr-1">
            <button
                type="button"
                data-test="tool-film-toggle"
                aria-label={playing ? 'Pausar video' : 'Reproducir video'}
                onClick={onToggle}
                className={button}
            >
                {playing ? (
                    <Pause aria-hidden className="size-4" />
                ) : (
                    <Play aria-hidden className="size-4" />
                )}
            </button>
            <button
                type="button"
                data-test="tool-film-sound"
                aria-label={muted ? 'Activar sonido' : 'Silenciar video'}
                aria-pressed={!muted}
                onClick={onToggleSound}
                className={button}
            >
                {muted ? (
                    <VolumeX aria-hidden className="size-4" />
                ) : (
                    <Volume2 aria-hidden className="size-4" />
                )}
            </button>
            {canFullscreen && (
                <button
                    type="button"
                    data-test="tool-film-fullscreen"
                    aria-label="Ver en pantalla completa"
                    onClick={onFullscreen}
                    className={button}
                >
                    <Maximize aria-hidden className="size-4" />
                </button>
            )}
        </div>
    );
}
