import { Lock } from 'lucide-react';
import RoomTourRow from '@/components/demos/room-tour-row';
import { BASE_MINUTES } from '@/lib/demos/room';
import type { DemoTour } from '@/types';

/** The tours of a new session in their order: «El centro y su gente» first and fixed, then the ones to choose. */
export default function RoomTourList({
    tours,
    chosen,
    onChange,
}: {
    tours: DemoTour[];
    chosen: string[];
    onChange: (key: string, change: { on: boolean } | { by: -1 | 1 }) => void;
}) {
    return (
        <ul className="flex flex-col divide-y rounded-xl border">
            <li className="flex items-center gap-3 bg-muted/50 px-4 py-3">
                <Lock
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="text-sm font-medium">
                        El centro y su gente
                    </span>
                    <span className="text-meta text-pretty text-muted-foreground max-sm:hidden">
                        Siempre primero: das de alta a tu público con su correo
                        y teléfono.
                    </span>
                    <span className="text-meta text-muted-foreground tabular-nums sm:hidden">
                        {`Siempre primero · ${BASE_MINUTES} min`}
                    </span>
                </div>
                <span className="shrink-0 text-meta text-muted-foreground tabular-nums max-sm:hidden">
                    {`${BASE_MINUTES} min`}
                </span>
            </li>
            {tours.map((tour, index) => (
                <RoomTourRow
                    key={tour.key}
                    tour={tour}
                    chosen={chosen.includes(tour.key)}
                    first={index === 0}
                    last={index === tours.length - 1}
                    onChange={(change) => onChange(tour.key, change)}
                />
            ))}
        </ul>
    );
}
