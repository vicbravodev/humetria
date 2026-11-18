import { WRAP } from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';
import type { ToolPageRelated } from '@/types';

/** Three tools that go with this one, each with the reason: one account for all of them. */
export default function ToolPageOthers({
    related,
}: {
    related: ToolPageRelated[];
}) {
    return (
        <section
            id="otras-herramientas"
            aria-labelledby="otras-herramientas-title"
            className="scroll-mt-16 border-t py-16"
        >
            <div className={cn(WRAP, 'grid gap-6')}>
                <Reveal>
                    <h2
                        id="otras-herramientas-title"
                        className="text-2xl font-semibold tracking-[-0.02em]"
                    >
                        Se usa junto con
                    </h2>
                </Reveal>
                <ul className="grid gap-x-8 md:grid-cols-3">
                    {related.map((tool, index) => (
                        <li
                            key={tool.key}
                            data-reveal="idle"
                            className={cn(
                                'reveal-rise',
                                `enter-delay-${index * 80}`,
                            )}
                        >
                            <a
                                href={tool.href}
                                data-track="tool_page_click"
                                data-track-target={tool.key}
                                className="group grid gap-1 border-t py-4 transition-colors duration-150 ease-out hover:text-primary"
                            >
                                <b className="text-lg">{tool.name}</b>
                                <span className="text-meta leading-relaxed text-muted-foreground">
                                    {tool.why}
                                </span>
                                <span className="pt-1 text-meta font-semibold text-primary">
                                    Ver {tool.name}
                                    <span
                                        aria-hidden
                                        className="ml-1 inline-block transition-transform duration-200 ease-out group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    >
                                        →
                                    </span>
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
