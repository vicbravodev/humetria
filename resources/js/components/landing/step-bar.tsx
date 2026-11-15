import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

/**
 * A thin track that fills to `value` (0 to 1) the first time it scrolls into view. The track is what the view watches:
 * the fill starts at zero width, and something with no size never counts as seen. The fill is an SVG `rect` whose width is
 * an attribute of presentation (a `style` would be blocked by the CSP) and the entrance scales it from the left.
 */
export default function StepBar({
    value,
    delay = 0.3,
}: {
    value: number;
    delay?: number;
}) {
    return (
        <span
            data-reveal="idle"
            className={cn(
                'block h-2 reveal-bar overflow-hidden rounded-full bg-border',
                enterDelay(delay),
            )}
        >
            <svg aria-hidden className="block size-full">
                <rect
                    width={`${value * 100}%`}
                    height="100%"
                    rx={4}
                    className="fill-primary"
                />
            </svg>
        </span>
    );
}
