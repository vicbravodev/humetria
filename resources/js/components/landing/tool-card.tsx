import { ArrowRight } from 'lucide-react';
import { TOOL_ICONS } from '@/components/landing/landing-families';
import { ICON_BOX } from '@/components/landing/landing-layout';
import type { LandingTool } from '@/components/landing/landing-tools';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

/** A tool on the landing: a real screen of it, its name, what it is about and whom it applies to, all a link to its page. */
export default function ToolCard({
    tool,
    thumb,
}: {
    tool: LandingTool;
    thumb: string;
}) {
    const Icon = TOOL_ICONS[tool.key];

    return (
        <a
            id={`herramienta-${tool.key}`}
            href={toolPageUrl(tool.key)}
            data-test={`tool-card-${tool.key}`}
            data-track="tool_page_click"
            data-track-target={tool.key}
            className="group grid h-full scroll-mt-24 content-start gap-3 overflow-hidden rounded-2xl border bg-card p-5 shadow-raised transition-colors duration-150 ease-out hover:border-primary"
        >
            <span
                aria-hidden
                className="-mx-5 -mt-5 mb-1 block h-36 overflow-hidden border-b bg-muted px-3 pt-3"
            >
                <img
                    src={`/images/tools/${thumb}-light.webp`}
                    alt=""
                    width={1520}
                    height={1100}
                    loading="lazy"
                    className="block w-[150%] max-w-none rounded-t-md dark:hidden"
                />
                <img
                    src={`/images/tools/${thumb}-dark.webp`}
                    alt=""
                    width={1520}
                    height={1100}
                    loading="lazy"
                    className="hidden w-[150%] max-w-none rounded-t-md dark:block"
                />
            </span>
            <span className="flex items-center justify-between gap-3">
                <span aria-hidden className={cn(ICON_BOX, 'size-11')}>
                    <Icon className="size-[22px]" />
                </span>
                <ArrowRight
                    aria-hidden
                    className="size-5 text-muted-foreground transition-transform duration-150 ease-out group-hover:translate-x-0.5 group-hover:text-primary motion-reduce:transition-none"
                />
            </span>
            <span className="grid gap-1">
                <span className="text-lg font-semibold">{tool.name}</span>
                <span
                    data-test="tool-hint"
                    className="text-meta text-muted-foreground"
                >
                    {tool.hint}
                </span>
            </span>
            <span className="text-meta leading-snug text-pretty">
                {tool.points[0]}
            </span>
            <span className="mt-auto text-caption text-muted-foreground">
                Aplica a: {tool.appliesTo}
            </span>
        </a>
    );
}
