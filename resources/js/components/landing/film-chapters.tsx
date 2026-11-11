import { chapterProgress } from '@/lib/film-chapters';
import type { FilmTiming } from '@/lib/film-chapters';
import { cn } from '@/lib/utils';

type Props = {
    labels: readonly string[];
    timing: FilmTiming;
    active: number;
    time: number;
    onJump: (chapter: number) => void;
};

/** Chapter navigation follows the film: the current chapter fills a thin line along its foot as it plays. */
export default function FilmChapters({
    labels,
    timing,
    active,
    time,
    onJump,
}: Props) {
    return (
        <ol
            aria-label="Capítulos del video"
            className="grid grid-cols-2 gap-px bg-input sm:grid-cols-4"
        >
            {labels.map((label, index) => (
                <li key={label} className="min-w-0">
                    <button
                        type="button"
                        data-test={`tool-chapter-${index}`}
                        aria-current={active === index ? 'true' : undefined}
                        onClick={() => onJump(index)}
                        className={cn(
                            'relative flex min-h-12 w-full items-center gap-2 px-3 py-3 text-left text-xs leading-4 font-medium transition-colors focus-visible:z-1 focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-current motion-reduce:transition-none sm:text-sm',
                            active === index
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-card text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        <span aria-hidden className="shrink-0 tabular-nums">
                            {index + 1}
                        </span>
                        <span>{label}</span>
                        {active === index && (
                            <span
                                aria-hidden
                                className="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-primary-foreground/70 transition-transform duration-300 ease-linear motion-reduce:transition-none"
                                style={{
                                    transform: `scaleX(${chapterProgress(timing, index, time)})`,
                                }}
                            />
                        )}
                    </button>
                </li>
            ))}
        </ol>
    );
}
