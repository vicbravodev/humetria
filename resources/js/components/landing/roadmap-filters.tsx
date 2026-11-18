import { AREA_LABELS } from '@/components/landing/roadmap-content';
import { WRAP } from '@/components/landing/landing-layout';
import type { AreaFilter } from '@/lib/roadmap';
import { cn } from '@/lib/utils';

const OPTIONS: readonly { key: AreaFilter; label: string }[] = [
    { key: 'all', label: 'Todo' },
    { key: 'evaluation', label: AREA_LABELS.evaluation },
    { key: 'compliance', label: AREA_LABELS.compliance },
    { key: 'talent', label: AREA_LABELS.talent },
    { key: 'operation', label: AREA_LABELS.operation },
];

/** The bar under the header that stays on screen: pick an area and only its tools remain, in every stage. */
export default function RoadmapFilters({
    area,
    counts,
    onChange,
}: {
    area: AreaFilter;
    counts: Record<AreaFilter, number>;
    onChange: (area: AreaFilter) => void;
}) {
    return (
        <div className="sticky top-16 z-[5] border-b bg-background/80 backdrop-blur-xl backdrop-saturate-150">
            <div
                role="group"
                aria-label="Filtrar por área"
                className={cn(
                    WRAP,
                    'flex [scrollbar-width:none] gap-2 overflow-x-auto py-3',
                )}
            >
                {OPTIONS.map((option) => (
                    <button
                        key={option.key}
                        type="button"
                        data-test={`area-${option.key}`}
                        aria-pressed={area === option.key}
                        onClick={() => onChange(option.key)}
                        className={cn(
                            'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            area === option.key
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-card text-foreground hover:bg-accent',
                        )}
                    >
                        {option.label}
                        <span
                            className={cn(
                                'tabular-nums',
                                area === option.key
                                    ? 'text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {counts[option.key]}
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}
