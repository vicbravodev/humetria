import { Check } from 'lucide-react';
import Reveal from '@/components/landing/reveal';
import ToolVisual from '@/components/landing/tool-visual';
import type { LandingTool } from '@/components/landing/landing-tools';

/** One tool: what it covers, whom it applies to, what it does, and a glimpse of it working. */
export default function ToolCard({ tool }: { tool: LandingTool }) {
    return (
        <Reveal>
            <article
                id={`herramienta-${tool.key}`}
                data-test="tool-card"
                aria-labelledby={`herramienta-${tool.key}-title`}
                className="grid scroll-mt-24 overflow-hidden card rounded-2xl md:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]"
            >
                <div className="flex flex-col gap-5 p-6 sm:p-8">
                    <div className="flex flex-col gap-1">
                        <h3
                            id={`herramienta-${tool.key}-title`}
                            className="text-2xl leading-8 font-semibold tracking-[-0.015em]"
                        >
                            {tool.name}
                        </h3>
                        <p
                            data-test="tool-hint"
                            className="text-muted-foreground"
                        >
                            {tool.hint}
                        </p>
                    </div>
                    <p className="text-sm">
                        <span className="font-medium">Aplica a:</span>{' '}
                        <span className="text-muted-foreground">
                            {tool.appliesTo}
                        </span>
                    </p>
                    <ul className="flex flex-col gap-2.5 border-t pt-5">
                        {tool.points.map((point) => (
                            <li key={point} className="flex gap-2.5">
                                <Check
                                    aria-hidden
                                    className="mt-1 size-4 shrink-0 text-primary"
                                />
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="flex items-center border-t bg-muted p-6 sm:p-8 md:border-t-0 md:border-l">
                    <ToolVisual tool={tool.key} />
                </div>
            </article>
        </Reveal>
    );
}
