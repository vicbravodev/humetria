import { Check } from 'lucide-react';
import type { LandingTool } from '@/components/landing/landing-tools';

export default function ToolCard({ tool }: { tool: LandingTool }) {
    return (
        <article
            data-test="tool-card"
            className="flex flex-col gap-4 rounded-lg border bg-card p-[26px]"
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-[22px] leading-[30px] font-semibold">
                    {tool.name}
                </h3>
                <p data-test="tool-hint" className="text-muted-foreground">
                    {tool.hint}
                </p>
            </div>
            <p className="rounded-md bg-muted px-3 py-2 text-sm">
                <span className="font-medium">Aplica a:</span> {tool.appliesTo}
            </p>
            <ul className="flex flex-col gap-2.5">
                {tool.points.map((point) => (
                    <li key={point} className="flex gap-2.5 text-sm">
                        <Check
                            aria-hidden
                            className="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        {point}
                    </li>
                ))}
            </ul>
        </article>
    );
}
