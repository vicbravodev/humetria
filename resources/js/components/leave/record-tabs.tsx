import { cn } from '@/lib/utils';

export type RecordTab = 'movements' | 'requests' | 'statements' | 'periods';

const TABS: { key: RecordTab; label: string }[] = [
    { key: 'movements', label: 'Movimientos' },
    { key: 'requests', label: 'Solicitudes' },
    { key: 'statements', label: 'Constancias' },
    { key: 'periods', label: 'Periodos' },
];

/** The parts of the record as tabs with how many each holds; the open one stays in the address (#movements, #requests...). */
export default function RecordTabs({
    selected,
    counts,
    onSelect,
}: {
    selected: RecordTab;
    counts: Record<RecordTab, number>;
    onSelect: (tab: RecordTab) => void;
}) {
    return (
        <div
            role="tablist"
            aria-label="Expediente de vacaciones"
            className="inline-flex max-w-full gap-0.5 overflow-x-auto rounded-[10px] border bg-muted p-[3px]"
        >
            {TABS.map(({ key, label }) => (
                <button
                    key={key}
                    type="button"
                    role="tab"
                    id={`record-tab-${key}`}
                    aria-selected={selected === key}
                    aria-controls={`record-panel-${key}`}
                    onClick={() => onSelect(key)}
                    className={cn(
                        'relative inline-flex h-[38px] shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-[3px] hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        selected === key &&
                            'bg-card text-foreground shadow-raised',
                    )}
                >
                    {label}
                    <span
                        className={cn(
                            'rounded-full px-[7px] text-xs leading-5 tabular-nums',
                            selected === key
                                ? 'bg-accent text-primary'
                                : 'bg-track text-muted-foreground',
                        )}
                    >
                        {counts[key]}
                    </span>
                </button>
            ))}
        </div>
    );
}
