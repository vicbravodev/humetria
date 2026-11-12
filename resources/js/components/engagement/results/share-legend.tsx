import { SHARE_BANDS } from '@/components/engagement/results/share-bands';
import { cn } from '@/lib/utils';

/** The legend of the three stretches of every question, once over the list. */
export default function ShareLegend() {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
            {SHARE_BANDS.map((band) => (
                <li key={band.key} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className={cn('size-2.5 rounded-[3px]', band.fill)}
                    />
                    {band.label}
                </li>
            ))}
        </ul>
    );
}
