import { ArrowRight, Check } from 'lucide-react';
import ToolIcon from '@/components/humetria/tool-icon';
import { FAMILY_CLASS, familyOf } from '@/components/landing/landing-families';
import type { LandingTool } from '@/components/landing/landing-tools';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

type Props = { tool: LandingTool; thumb: string; index: number };

const SHOT =
    'block size-full rounded-t-lg border border-b-0 object-cover object-top-left shadow-raised';

/**
 * A tool on the landing, in its family's color, the same height as its neighbours: its real screen in a box of fixed
 * proportion, then one line of what it is about, its name and two lines of what it does. A wide card puts the screen
 * beside the text and uses the room for the rest of what the tool does and whom it applies to.
 */
export default function ToolCard({ tool, thumb, index }: Props) {
    return (
        <a
            id={`herramienta-${tool.key}`}
            href={toolPageUrl(tool.key)}
            data-test={`tool-card-${tool.key}`}
            data-track="tool_page_click"
            data-track-target={tool.key}
            data-reveal="idle"
            className={cn(
                FAMILY_CLASS[familyOf(tool.key).key],
                'group @container block h-full scroll-mt-24 overflow-hidden rounded-2xl border bg-card shadow-raised transition-colors duration-200 ease-out reveal-rise hover:border-family',
                `enter-delay-${index * 70}`,
            )}
        >
            <span className="grid h-full @lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <span
                    aria-hidden
                    className="flex flex-col bg-family-soft px-5 pt-5"
                >
                    <span className="block aspect-[1520/1100] overflow-hidden transition-transform duration-300 ease-out group-hover:-translate-y-1 motion-reduce:transition-none @lg:aspect-auto @lg:min-h-0 @lg:flex-1">
                        <img
                            src={`/images/tools/${thumb}-light.webp`}
                            alt=""
                            width={1520}
                            height={1100}
                            loading="lazy"
                            className={cn(SHOT, 'dark:hidden')}
                        />
                        <img
                            src={`/images/tools/${thumb}-dark.webp`}
                            alt=""
                            width={1520}
                            height={1100}
                            loading="lazy"
                            className={cn(SHOT, 'hidden dark:block')}
                        />
                    </span>
                </span>
                <span className="grid content-start gap-1.5 p-5">
                    <span
                        data-test="tool-hint"
                        className="flex min-w-0 items-center gap-1.5 text-caption font-semibold text-family"
                    >
                        <ToolIcon tool={tool.key} className="size-4" />
                        <span className="truncate">{tool.hint}</span>
                    </span>
                    <span className="flex items-center justify-between gap-3 text-lg font-semibold">
                        {tool.name}
                        <ArrowRight
                            aria-hidden
                            className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-hover:translate-x-0.5 group-hover:text-family motion-reduce:transition-none"
                        />
                    </span>
                    <span className="line-clamp-2 min-h-[2lh] text-meta leading-snug text-pretty text-muted-foreground">
                        {tool.points[0]}
                    </span>
                    <span className="mt-2 hidden gap-2 border-t pt-3 @lg:grid">
                        {tool.points.slice(1, 4).map((point) => (
                            <span
                                key={point}
                                className="grid grid-cols-[16px_minmax(0,1fr)] gap-2 text-caption leading-snug"
                            >
                                <Check
                                    aria-hidden
                                    className="mt-0.5 size-4 text-family"
                                />
                                {point}
                            </span>
                        ))}
                        <span className="pt-1 text-caption text-muted-foreground">
                            Aplica a: {tool.appliesTo}
                        </span>
                    </span>
                </span>
            </span>
        </a>
    );
}
