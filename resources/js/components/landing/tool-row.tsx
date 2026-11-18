import { ArrowRight } from 'lucide-react';
import { FAMILY_CLASS, familyOf } from '@/components/landing/landing-families';
import type { LandingTool } from '@/components/landing/landing-tools';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

type Props = { tool: LandingTool; thumb: string; index: number };

const SHOT =
    'block size-full object-cover object-top-left transition-transform duration-300 ease-out group-hover:-translate-y-0.5 motion-reduce:transition-none';

/**
 * A tool: its real screen, what it is about, its name and one line of what it does. On a phone it is a row of its family's
 * list (the screen small on the left); from `md` up the screen comes first and wide, with its caption under it, no frame
 * around the whole. Every tool says the same amount; the rest lives on the tool's own page, one click away.
 */
export default function ToolRow({ tool, thumb, index }: Props) {
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
                'group grid scroll-mt-24 grid-cols-[96px_minmax(0,1fr)] items-center gap-x-4 py-4 reveal-rise focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring md:grid-cols-1 md:items-start md:gap-y-4 md:py-0',
                `enter-delay-${index * 70}`,
            )}
        >
            <span
                aria-hidden
                className="block aspect-[16/10] overflow-hidden rounded-md border bg-family-soft md:rounded-lg"
            >
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
            <span className="grid min-w-0 gap-1">
                <span
                    data-test="tool-hint"
                    className="truncate text-caption font-semibold text-family"
                >
                    {tool.hint}
                </span>
                <span className="flex items-center gap-2 text-lg leading-tight font-semibold transition-colors duration-150 group-hover:text-family md:text-xl">
                    {tool.name}
                    <ArrowRight
                        aria-hidden
                        className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-hover:translate-x-0.5 group-hover:text-family motion-reduce:transition-none"
                    />
                </span>
                <span className="line-clamp-2 text-meta leading-snug text-pretty text-muted-foreground">
                    {tool.points[0]}
                </span>
            </span>
        </a>
    );
}
