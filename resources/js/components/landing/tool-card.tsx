import { Check } from 'lucide-react';
import type { LandingTool } from '@/components/landing/landing-tools';

export default function ToolCard({ tool }: { tool: LandingTool }) {
    const { icon: Icon } = tool;

    return (
        // Two rows of the section's grid, so every card's list starts at the same height.
        <article
            data-test="tool-card"
            className="row-span-2 grid grid-rows-subgrid gap-0 rounded-lg border bg-card p-6 sm:p-8"
        >
            <div>
                <span className="flex size-11 items-center justify-center rounded-md bg-accent">
                    <Icon aria-hidden className="size-6 text-primary" />
                </span>
                <h3 className="mt-6 text-xl leading-7 font-semibold">
                    {tool.name}
                </h3>
                <p
                    data-test="tool-official"
                    className="mt-1 text-sm text-muted-foreground"
                >
                    {tool.official}
                </p>
                <p className="mt-4 text-sm">
                    <span className="font-medium">Aplica a:</span>{' '}
                    {tool.appliesTo}
                </p>
            </div>
            <ul className="mt-6 space-y-3 self-start border-t pt-6">
                {tool.points.map((point) => (
                    <li key={point} className="flex gap-3">
                        <Check
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0 text-primary"
                        />
                        <span className="text-muted-foreground">{point}</span>
                    </li>
                ))}
            </ul>
        </article>
    );
}
