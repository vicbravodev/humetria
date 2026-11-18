import { ArrowRight } from 'lucide-react';
import {
    FAMILY_CLASS,
    familyOf,
    TOOL_ICONS,
} from '@/components/landing/landing-families';
import type { LandingTool } from '@/components/landing/landing-tools';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

type Props = { tool: LandingTool; thumb: string; index: number };

/**
 * A tool on the landing, in its family's color: its real screen, whole, on top (beside the text when the card is wide), and
 * under it its name, what it is about and what it does, all a link to its page.
 */
export default function ToolCard({ tool, thumb, index }: Props) {
    const Icon = TOOL_ICONS[tool.key];
    const shot =
        'block h-auto w-full rounded-t-lg border border-b-0 shadow-raised transition-transform duration-300 ease-out group-hover:-translate-y-1 motion-reduce:transition-none';

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
            <span className="grid h-full @xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <span
                    aria-hidden
                    className="flex items-end overflow-hidden bg-family-soft px-5 pt-5 @xl:pr-0"
                >
                    <img
                        src={`/images/tools/${thumb}-light.webp`}
                        alt=""
                        width={1520}
                        height={1100}
                        loading="lazy"
                        className={cn(shot, '@xl:rounded-tr-none dark:hidden')}
                    />
                    <img
                        src={`/images/tools/${thumb}-dark.webp`}
                        alt=""
                        width={1520}
                        height={1100}
                        loading="lazy"
                        className={cn(
                            shot,
                            'hidden @xl:rounded-tr-none dark:block',
                        )}
                    />
                </span>
                <span className="grid content-start gap-1.5 p-5">
                    <span
                        data-test="tool-hint"
                        className="flex items-center gap-1.5 text-caption font-semibold text-family"
                    >
                        <Icon aria-hidden className="size-4" />
                        {tool.hint}
                    </span>
                    <span className="flex items-center justify-between gap-3 text-lg font-semibold">
                        {tool.name}
                        <ArrowRight
                            aria-hidden
                            className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-hover:translate-x-0.5 group-hover:text-family motion-reduce:transition-none"
                        />
                    </span>
                    <span className="text-meta leading-snug text-pretty text-muted-foreground">
                        {tool.points[0]}
                    </span>
                </span>
            </span>
        </a>
    );
}
