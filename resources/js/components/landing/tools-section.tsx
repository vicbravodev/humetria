import { ArrowRight } from 'lucide-react';
import {
    FAMILIES,
    familyTools,
    TOOL_ICONS,
} from '@/components/landing/landing-families';
import {
    ICON_BOX,
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

// On a wide screen a family takes one row, however many tools it has: no orphan card.
const COLUMNS: Record<number, string> = {
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
};

/** Nine tools as three families, each a card that leads to the tool's own page with its film, the law and the questions. */
export default function ToolsSection() {
    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className={cn('scroll-mt-16 border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'flex flex-col gap-10')}>
                <Reveal className="flex max-w-[760px] flex-col gap-4">
                    <h2 id="herramientas-title" className={TITLE}>
                        Nueve herramientas, un solo lugar
                    </h2>
                    <p className={LEAD}>
                        Cada una con su página: qué pide la norma, cómo funciona
                        y un video de la herramienta. Humi te acompaña en todas.
                    </p>
                </Reveal>
                {FAMILIES.map((family) => (
                    <div
                        key={family.key}
                        id={`familia-${family.key}`}
                        data-test={`family-${family.key}`}
                        className="grid scroll-mt-24 gap-4"
                    >
                        <h3 className="flex items-baseline gap-3 text-xl font-semibold">
                            {family.name}
                            <span className="text-meta font-normal text-muted-foreground">
                                {family.hint}
                            </span>
                        </h3>
                        <ul
                            className={cn(
                                'grid gap-3 sm:grid-cols-2',
                                COLUMNS[family.tools.length],
                            )}
                        >
                            {familyTools(family).map((tool) => {
                                const Icon = TOOL_ICONS[tool.key];

                                return (
                                    <li key={tool.key}>
                                        <a
                                            id={`herramienta-${tool.key}`}
                                            href={toolPageUrl(tool.key)}
                                            data-test={`tool-card-${tool.key}`}
                                            data-track="tool_page_click"
                                            data-track-target={tool.key}
                                            className="group grid h-full scroll-mt-24 content-start gap-3 rounded-2xl border bg-card p-5 shadow-raised transition-colors duration-150 ease-out hover:border-primary"
                                        >
                                            <span className="flex items-center justify-between gap-3">
                                                <span
                                                    aria-hidden
                                                    className={cn(
                                                        ICON_BOX,
                                                        'size-11',
                                                    )}
                                                >
                                                    <Icon className="size-[22px]" />
                                                </span>
                                                <ArrowRight
                                                    aria-hidden
                                                    className="size-5 text-muted-foreground transition-transform duration-150 ease-out group-hover:translate-x-0.5 group-hover:text-primary motion-reduce:transition-none"
                                                />
                                            </span>
                                            <span className="grid gap-1">
                                                <span className="text-lg font-semibold">
                                                    {tool.name}
                                                </span>
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
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
