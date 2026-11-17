import { ChevronRight } from 'lucide-react';
import { LEAD, TITLE, WRAP } from '@/components/landing/landing-layout';
import type { LandingTool } from '@/components/landing/landing-tools';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { ToolPageContent } from '@/types';

type Props = { page: ToolPageContent; tool: LandingTool | undefined };

/** Where the page sits, what the tool solves in one line, the two ways on, and its film. */
export default function ToolPageHero({ page, tool }: Props) {
    return (
        <section
            id="inicio"
            aria-labelledby="tool-title"
            className={cn(WRAP, 'grid gap-8 pt-8 pb-12 sm:pt-10 lg:pb-16')}
        >
            <nav aria-label="Migas de pan">
                <ol className="flex items-center gap-1.5 text-meta text-muted-foreground">
                    <li>
                        <a
                            href={home().url}
                            className="inline-flex min-h-11 items-center rounded-sm hover:text-foreground"
                        >
                            Inicio
                        </a>
                    </li>
                    <li aria-hidden>
                        <ChevronRight className="size-3.5" />
                    </li>
                    <li aria-current="page" className="text-foreground">
                        {page.name}
                    </li>
                </ol>
            </nav>
            <div className="grid gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-center lg:gap-12">
                <div className="grid content-start gap-5">
                    {tool === undefined ? null : (
                        <p className="text-meta font-medium text-primary">
                            {tool.hint}
                        </p>
                    )}
                    <h1 id="tool-title" className={TITLE}>
                        {page.h1}
                    </h1>
                    <p className={LEAD}>{page.lead}</p>
                    <div className="flex flex-wrap gap-3 pt-1">
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
                    {tool === undefined ? null : (
                        <p className="grid gap-1 rounded-xl bg-sidebar p-4 text-meta">
                            <small className="text-caption text-muted-foreground">
                                Aplica a
                            </small>
                            <b className="leading-snug font-semibold">
                                {tool.appliesTo}
                            </b>
                        </p>
                    )}
                </div>
                {tool === undefined ? null : (
                    <ToolFilmPlayer film={tool.film} />
                )}
            </div>
        </section>
    );
}
