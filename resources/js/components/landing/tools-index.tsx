import { useState } from 'react';
import GlideChips from '@/components/landing/glide-chips';
import { TOOLS } from '@/components/landing/landing-tools';
import { useActiveSection } from '@/hooks/use-active-section';
import { cn } from '@/lib/utils';

const ids = TOOLS.map((tool) => `herramienta-${tool.key}`);

/** The five tools as a list that stays in view beside them, marking the one being read. Wide screens only. */
export default function ToolsIndex() {
    const active = useActiveSection(ids);
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <nav aria-label="Herramientas" className="hidden lg:block">
            <ol
                onMouseLeave={() => setHovered(null)}
                className="sticky top-24 flex flex-col gap-1"
            >
                {TOOLS.map((tool, index) => {
                    const current = active === `herramienta-${tool.key}`;

                    return (
                        <li key={tool.key} className="relative">
                            <GlideChips
                                group="tools"
                                current={current}
                                hovered={hovered === tool.key}
                                className="rounded-xl"
                            />
                            <a
                                href={`#herramienta-${tool.key}`}
                                data-test={`tool-link-${tool.key}`}
                                aria-current={current ? 'true' : undefined}
                                onMouseEnter={() => setHovered(tool.key)}
                                onFocus={() => setHovered(tool.key)}
                                onBlur={() => setHovered(null)}
                                className={cn(
                                    'relative flex min-h-11 items-center gap-3 rounded-xl px-3.5 py-2.5 text-lg font-medium transition-colors duration-200 ease-out',
                                    current
                                        ? 'text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'w-6 text-sm tabular-nums',
                                        current
                                            ? 'text-primary-foreground'
                                            : 'text-primary',
                                    )}
                                >
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                {tool.name}
                            </a>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
