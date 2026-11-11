import { TOOLS, type ToolKey } from '@/components/landing/landing-tools';
import { useToolTabs } from '@/hooks/use-tool-tabs';
import { cn } from '@/lib/utils';

const KEYS = TOOLS.map((tool) => tool.key);

type Props = {
    selected: ToolKey;
    onSelect: (key: ToolKey) => void;
};

export default function ToolTabs({ selected, onSelect }: Props) {
    const move = useToolTabs(KEYS, selected, onSelect);

    return (
        <div className="relative min-w-0 lg:sticky lg:top-20">
            <div
                role="tablist"
                aria-label="Herramientas"
                className="flex scroll-px-5 gap-1 overflow-x-auto overscroll-x-contain p-1 pr-5 lg:max-h-[calc(100svh-6rem)] lg:flex-col lg:overflow-y-auto lg:pr-1"
            >
                {TOOLS.map((tool, index) => {
                    const current = selected === tool.key;

                    return (
                        <button
                            key={tool.key}
                            type="button"
                            role="tab"
                            id={`tool-tab-${tool.key}`}
                            data-test={`tool-tab-${tool.key}`}
                            aria-selected={current}
                            aria-controls="tool-panel"
                            tabIndex={current ? 0 : -1}
                            onClick={() => onSelect(tool.key)}
                            onKeyDown={(event) => move(event, index)}
                            className={cn(
                                'flex min-h-16 w-56 shrink-0 items-start gap-2.5 rounded-lg px-3 py-2 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none lg:w-full',
                                current
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-foreground hover:bg-accent',
                            )}
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'pt-0.5 text-xs leading-5 tabular-nums',
                                    current
                                        ? 'text-primary-foreground'
                                        : 'text-primary',
                                )}
                            >
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="text-sm leading-5 font-semibold">
                                    {tool.name}
                                </span>
                                <span
                                    data-test="tool-hint"
                                    className={cn(
                                        'text-xs leading-4 text-pretty',
                                        current
                                            ? 'text-primary-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {tool.hint}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
            <span
                aria-hidden
                className="pointer-events-none absolute inset-y-0 right-0 w-5 bg-linear-to-l from-background to-transparent lg:hidden"
            />
        </div>
    );
}
