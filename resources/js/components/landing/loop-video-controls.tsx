import { Pause, Play, RotateCcw, Volume2, VolumeX } from 'lucide-react';

type Props = {
    test: string;
    playing: boolean;
    muted: boolean;
    onRestart: () => void;
    onToggle: () => void;
    onToggleSound: () => void;
};

const control =
    'flex size-11 shrink-0 items-center justify-center rounded-full border bg-card text-foreground transition-transform duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.94]';

/** The film's three buttons under it: back to the start, the voice and play or pause. */
export default function LoopVideoControls({
    test,
    playing,
    muted,
    onRestart,
    onToggle,
    onToggleSound,
}: Props) {
    return (
        <div className="flex shrink-0 items-center gap-2">
            <button
                type="button"
                data-test={`${test}-restart`}
                aria-label="Ver desde el inicio"
                onClick={onRestart}
                className={control}
            >
                <RotateCcw aria-hidden className="size-4" />
            </button>
            <button
                type="button"
                data-test={`${test}-sound`}
                aria-label={muted ? 'Activar sonido' : 'Silenciar video'}
                aria-pressed={!muted}
                onClick={onToggleSound}
                className={control}
            >
                {muted ? (
                    <VolumeX aria-hidden className="size-4" />
                ) : (
                    <Volume2 aria-hidden className="size-4" />
                )}
            </button>
            <button
                type="button"
                data-test={`${test}-toggle`}
                aria-label={playing ? 'Pausar video' : 'Reproducir video'}
                onClick={onToggle}
                className={control}
            >
                {playing ? (
                    <Pause aria-hidden className="size-4" />
                ) : (
                    <Play aria-hidden className="size-4 translate-x-px" />
                )}
            </button>
        </div>
    );
}
