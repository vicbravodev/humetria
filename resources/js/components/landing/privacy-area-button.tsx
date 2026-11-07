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
                'inline-flex min-h-11 items-center gap-2 rounded-md border px-3.5 text-sm font-medium transition-[background-color,border-color,color,transform] duration-150 ease-out active:scale-[0.97] motion-reduce:active:scale-100',
                pressed
                    ? 'border-primary bg-accent text-accent-foreground'
                    : 'border-input bg-card hover:bg-accent',
            )}
        >
            {area.name}
            <span className="text-muted-foreground tabular-nums">
                {area.answers}
            </span>
        </button>
    );
}
