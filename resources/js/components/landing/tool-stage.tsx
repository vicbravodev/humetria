import { Check } from 'lucide-react';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import type { LandingTool } from '@/components/landing/landing-tools';

/** The tool picked: its film large on top, and under it what it covers, whom it applies to and what it does. */
export default function ToolStage({ tool }: { tool: LandingTool }) {
    return (
        <section
            id="tool-panel"
            role="tabpanel"
            data-test="tool-panel"
            aria-labelledby={`tool-tab-${tool.key}`}
            className="min-w-0 card rounded-2xl"
        >
            <ToolFilmPlayer key={tool.key} film={tool.film} />
            <div className="grid gap-4 p-4 text-sm leading-5 md:grid-cols-[160px_minmax(0,1fr)] md:gap-6">
                <p className="flex flex-col gap-1">
                    <span className="font-medium">Aplica a:</span>{' '}
                    <span className="text-muted-foreground">
                        {tool.appliesTo}
                    </span>
                </p>
                <ul className="grid gap-x-5 gap-y-2 sm:grid-cols-2">
                    {tool.points.map((point) => (
                        <li key={point} className="flex items-start gap-2">
                            <Check
                                aria-hidden
                                className="mt-0.5 size-3.5 shrink-0 text-primary"
                            />
                            {point}
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
