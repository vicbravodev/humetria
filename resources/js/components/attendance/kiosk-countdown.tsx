import { RefreshCw } from 'lucide-react';

/** When the code turns, in seconds and as a bar that empties: legible from far away. */
export default function KioskCountdown({
    left,
    window: seconds,
}: {
    left: number;
    window: number;
}) {
    return (
        <div className="flex flex-col gap-4 card p-5">
            <div className="flex items-center gap-3">
                <RefreshCw aria-hidden className="size-6 text-primary" />
                <div className="flex flex-col">
                    <p className="text-2xl font-semibold tabular-nums">
                        Cambia en {left} s
                    </p>
                    <p className="text-base text-muted-foreground">
                        Un código nuevo cada {seconds} segundos
                    </p>
                </div>
            </div>
            <div
                aria-hidden
                className="h-2 w-full overflow-hidden rounded-full bg-track"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-1000 ease-linear motion-reduce:transition-none"
                    style={{
                        width: `${Math.min(100, (left / seconds) * 100)}%`,
                    }}
                />
            </div>
        </div>
    );
}
