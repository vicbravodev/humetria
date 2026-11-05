import type { CompareBar } from '@/lib/compensation/scenario-draft';
import { cn } from '@/lib/utils';

/** «Frente a tus otros escenarios»: one bar each, in one tone, this one in the accent. Only this account's scenarios (D1). */
export default function ScenarioCompareBars({ bars }: { bars: CompareBar[] }) {
    return (
        <section
            aria-labelledby="scenario-compare-title"
            className="grid gap-3"
        >
            <h3 id="scenario-compare-title" className="font-medium">
                Frente a tus otros escenarios
            </h3>
            <ul className="grid gap-3">
                {bars.map((bar) => (
                    <li key={bar.key} className="grid gap-1.5">
                        <span
                            className={cn(
                                'flex justify-between gap-3 text-sm',
                                bar.current
                                    ? 'font-semibold'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <span className="min-w-0 truncate">{bar.name}</span>
                            <span className="tabular-nums">{bar.text}</span>
                        </span>
                        <span
                            aria-hidden
                            className="block h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                className={cn(
                                    'block h-full rounded-full',
                                    bar.current
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/60',
                                )}
                                style={{ width: `${bar.percent}%` }}
                            />
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
