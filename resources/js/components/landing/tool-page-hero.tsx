import ToolIcon from '@/components/humetria/tool-icon';
import { LEAD, TITLE, WRAP } from '@/components/landing/landing-layout';
import type { LandingTool } from '@/components/landing/landing-tools';
import ToolPageCrumbs from '@/components/landing/tool-page-crumbs';
import ToolPageStage from '@/components/landing/tool-page-stage';
import { Button } from '@/components/ui/button';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';
import type { ToolPageContent } from '@/types';

/** Each part of the hero rises in after the one before, as the landing's does (CSS, `enter-after`). */
const after = (delay: number) => cn('enter-after', enterDelay(delay));

type Props = { page: ToolPageContent; tool: LandingTool | undefined };

/** Where the page sits, the tool's mark, what it solves, the two ways on and what proves it; beside it, its film. */
export default function ToolPageHero({ page, tool }: Props) {
    const urgent = page.key === 'attendance';

    return (
        <section
            id="inicio"
            aria-labelledby="tool-title"
            className={cn(WRAP, 'grid gap-6 pt-4 pb-14 lg:pb-20')}
        >
            <ToolPageCrumbs page={page} />
            <div className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-center lg:gap-14">
                <div className="grid content-start gap-5">
                    <div
                        className={cn(
                            'flex flex-wrap items-center gap-3',
                            after(0.05),
                        )}
                    >
                        {tool === undefined ? null : (
                            <span className="grid size-12 place-items-center rounded-2xl bg-family-soft text-family">
                                <ToolIcon tool={tool.key} className="size-7" />
                            </span>
                        )}
                        <p
                            className={cn(
                                'rounded-full px-3 py-1 text-meta font-semibold',
                                urgent
                                    ? 'bg-family text-background'
                                    : 'bg-family-soft text-family',
                            )}
                        >
                            {page.chip}
                        </p>
                    </div>
                    <h1 id="tool-title" className={cn(TITLE, after(0.1))}>
                        {page.h1}
                    </h1>
                    <p className={cn(LEAD, after(0.2))}>{page.lead}</p>
                    <div
                        className={cn('flex flex-wrap gap-3 pt-1', after(0.3))}
                    >
                        <Button asChild size="lg" className="rounded-full">
                            <a
                                href="#agenda-una-demo"
                                data-track="cta_click"
                                data-track-target="hero_demo"
                            >
                                Agenda una demo
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="rounded-full"
                        >
                            <a
                                href="#como-funciona"
                                data-track="cta_click"
                                data-track-target="hero_how_it_works"
                            >
                                Ver cómo funciona
                            </a>
                        </Button>
                    </div>
                    <p
                        className={cn(
                            'max-w-[60ch] text-meta leading-relaxed text-muted-foreground',
                            after(0.4),
                        )}
                    >
                        {page.proof}
                    </p>
                </div>
                <ToolPageStage tool={tool} />
            </div>
        </section>
    );
}
