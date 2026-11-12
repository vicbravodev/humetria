import type { PrivacyArea } from '@/components/landing/privacy-areas';
import { cn } from '@/lib/utils';

/** One filter of the example: the area and how many answered in it. */
export default function PrivacyAreaButton({
    area,
    pressed,
    onPress,
}: {
    area: PrivacyArea;
    pressed: boolean;
    onPress: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={pressed}
            data-test={`privacy-area-${area.key}`}
            onClick={onPress}
            className={cn(
                'inline-flex min-h-11 items-center gap-2 rounded-xl border px-3.5 text-meta transition-[background-color,border-color,color,transform] duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.97] motion-reduce:active:scale-100',
                pressed
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-input bg-card hover:border-foreground',
            )}
        >
            {area.name}
            <span
                className={cn(
                    'text-sm tabular-nums',
                    pressed ? 'opacity-90' : 'text-muted-foreground',
                )}
            >
                {area.answers}
            </span>
        </button>
    );
}
