/**
 * The rule of the recommendation index, −100 to 100, with zero marked and a dot where the index sits: so "−27" reads as
 * "a quarter of the way below zero", not as a loose number.
 */
export default function RecommendationScale({ index }: { index: number }) {
    const at = (Math.max(-100, Math.min(100, index)) + 100) / 2;

    return (
        <div aria-hidden className="flex flex-col gap-1.5">
            <div className="relative h-4">
                <span className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-track" />
                <span className="absolute top-0 left-1/2 h-4 w-0.5 -translate-x-1/2 bg-foreground" />
                <span
                    className="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-card bg-primary ring-2 ring-primary"
                    style={{ left: `${at}%` }}
                />
            </div>
            <div className="flex justify-between text-caption text-muted-foreground tabular-nums">
                <span>−100</span>
                <span>0</span>
                <span>100</span>
            </div>
        </div>
    );
}
