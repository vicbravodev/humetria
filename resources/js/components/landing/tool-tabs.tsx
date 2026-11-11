import GlideChips from '@/components/landing/glide-chips';
import { ICON_BOX } from '@/components/landing/landing-layout';
import {
    familyTools,
    TOOL_ICONS,
    type LandingFamily,
} from '@/components/landing/landing-families';
import type { ToolKey } from '@/components/landing/landing-tools';
import { useToolTabs } from '@/hooks/use-tool-tabs';
import { cn } from '@/lib/utils';

type Props = {
    family: LandingFamily;
    selected: ToolKey;
    onSelect: (key: ToolKey) => void;
};

/** The tools of the chosen family: a strip that scrolls sideways on a phone, a column that stays in view on a wide screen. */
export default function ToolTabs({ family, selected, onSelect }: Props) {
    const move = useToolTabs(family.tools, selected, onSelect);
    const tools = familyTools(family);

    return (
        <div className="relative -mx-5 min-w-0 sm:-mx-8 lg:sticky lg:top-26 lg:mx-0">
            <div
                role="tablist"
                id="tool-rail"
                data-test="tool-rail"
                aria-label={`Herramientas de ${family.name}`}
                className="flex scroll-px-5 gap-2 overflow-x-auto overscroll-x-contain px-5 py-1 sm:px-8 lg:flex-col lg:overflow-visible lg:p-0"
            >
                {tools.map((tool, index) => {
                    const current = selected === tool.key;
                    const Icon = TOOL_ICONS[tool.key];

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
                                'relative grid min-h-16 w-44 shrink-0 grid-cols-[36px_minmax(0,1fr)] items-center gap-x-2.5 rounded-xl border py-2.5 pr-3.5 pl-2.5 text-left transition-[border-color,transform] duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.98] lg:min-h-18 lg:w-full',
                                current
                                    ? 'border-transparent'
                                    : 'bg-card hover:border-input',
                            )}
                        >
                            <GlideChips
                                group="tool"
                                current={current}
                                hovered={false}
                                className="rounded-xl border border-primary bg-accent shadow-none"
                            />
                            <span
                                className={`${ICON_BOX} relative row-span-2 size-9 rounded-[10px]`}
                            >
                                <Icon aria-hidden className="size-5" />
                            </span>
                            <span className="relative self-end text-meta leading-tight font-semibold">
                                {tool.name}
                            </span>
                            <span
                                data-test="tool-hint"
                                className="relative self-start text-caption leading-tight text-muted-foreground"
                            >
                                {tool.hint}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
