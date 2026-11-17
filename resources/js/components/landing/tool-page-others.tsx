import { ArrowRight } from 'lucide-react';
import { TOOL_ICONS } from '@/components/landing/landing-families';
import { ICON_BOX, SECTION, WRAP } from '@/components/landing/landing-layout';
import { TOOLS } from '@/components/landing/landing-tools';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

/** The other eight tools, each to its own page: one account for all of them. */
export default function ToolPageOthers({ current }: { current: string }) {
    return (
        <section
            id="otras-herramientas"
            aria-labelledby="otras-herramientas-title"
            className={cn('scroll-mt-16 border-t', SECTION)}
        >
            <div className={cn(WRAP, 'grid gap-8')}>
                <h2
                    id="otras-herramientas-title"
                    className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl"
                >
                    Más herramientas en la misma cuenta
                </h2>
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {TOOLS.filter((tool) => tool.key !== current).map(
                        (tool) => {
                            const Icon = TOOL_ICONS[tool.key];

                            return (
                                <li key={tool.key}>
                                    <a
                                        href={toolPageUrl(tool.key)}
                                        data-track="tool_page_click"
                                        data-track-target={tool.key}
                                        className="group grid h-full grid-cols-[40px_minmax(0,1fr)_auto] items-center gap-3 rounded-2xl border bg-card p-4 transition-colors duration-150 ease-out hover:border-primary"
                                    >
                                        <span
                                            aria-hidden
                                            className={cn(ICON_BOX, 'size-10')}
                                        >
                                            <Icon className="size-5" />
                                        </span>
                                        <span className="grid gap-0.5">
                                            <span className="font-semibold">
                                                {tool.name}
                                            </span>
                                            <span className="text-caption text-muted-foreground">
                                                {tool.hint}
                                            </span>
                                        </span>
                                        <ArrowRight
                                            aria-hidden
                                            className="size-4 text-muted-foreground transition-transform duration-150 ease-out group-hover:translate-x-0.5 group-hover:text-primary motion-reduce:transition-none"
                                        />
                                    </a>
                                </li>
                            );
                        },
                    )}
                </ul>
            </div>
        </section>
    );
}
