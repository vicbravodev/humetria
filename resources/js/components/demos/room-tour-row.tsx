import { ArrowDown, ArrowUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { DemoTour } from '@/types';

/** A tour to choose for the session: what it shows, how long it takes, and its place in the order (no «Subir» first, no «Bajar» last). */
export default function RoomTourRow({
    tour,
    chosen,
    first,
    last,
    onChange,
}: {
    tour: DemoTour;
    chosen: boolean;
    first: boolean;
    last: boolean;
    onChange: (change: { on: boolean } | { by: -1 | 1 }) => void;
}) {
    const id = `tour-${tour.key}`;

    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <Checkbox
                id={id}
                checked={chosen}
                onCheckedChange={(on) => onChange({ on: on === true })}
            />
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <label htmlFor={id} className="text-sm font-medium">
                    {tour.title}
                </label>
                {/* On a phone only how long it takes: the summary would squeeze the row. */}
                <span className="text-meta text-pretty text-muted-foreground max-sm:hidden">
                    {tour.summary}
                </span>
                <span className="text-meta text-muted-foreground tabular-nums sm:hidden">
                    {`${tour.minutes} min`}
                </span>
            </div>
            <span className="shrink-0 text-meta text-muted-foreground tabular-nums max-sm:hidden">
                {`${tour.minutes} min`}
            </span>
            <div className="flex shrink-0">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Subir ${tour.title}`}
                    disabled={first}
                    onClick={() => onChange({ by: -1 })}
                >
                    <ArrowUp aria-hidden />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Bajar ${tour.title}`}
                    disabled={last}
                    onClick={() => onChange({ by: 1 })}
                >
                    <ArrowDown aria-hidden />
                </Button>
            </div>
        </li>
    );
}
