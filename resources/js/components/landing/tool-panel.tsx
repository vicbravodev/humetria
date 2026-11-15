import { Check } from 'lucide-react';
import LandingHumi from '@/components/landing/landing-humi';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import type { LandingTool } from '@/components/landing/landing-tools';
import { cn } from '@/lib/utils';

type Props = { tool: LandingTool; selected: boolean };

/**
 * One tool: its name and what it is about, whom it applies to, what it does and Humi with it; the chosen one adds its
 * film large. The unchosen ones hide only when <html> has `js` (the inline script of the document): without JavaScript
 * the nine read stacked, each with its heading, and with it the heading of the chosen one is for screen readers because
 * the tab already shows the name.
 */
export default function ToolPanel({ tool, selected }: Props) {
    return (
        <div
            id={`tool-panel-${tool.key}`}
            role="tabpanel"
            data-test={selected ? 'tool-panel' : 'tool-panel-idle'}
            aria-labelledby={`tool-panel-title-${tool.key}`}
            className={cn(
                'grid min-w-0 gap-5 rounded-3xl border bg-card p-3.5 shadow-raised sm:p-5',
                selected
                    ? 'lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6'
                    : '[html.js_&]:hidden',
            )}
        >
            <div className="grid gap-1 lg:col-span-2 [html.js_&]:sr-only">
                <h3
                    id={`tool-panel-title-${tool.key}`}
                    className="text-lg font-semibold"
                >
                    {tool.name}
                </h3>
                <p className="text-meta text-muted-foreground">{tool.hint}</p>
            </div>
            {selected && <ToolFilmPlayer film={tool.film} />}
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-4',
                    selected && 'enter-details',
                )}
            >
                <p className="grid gap-1 rounded-xl bg-sidebar p-4">
                    <small className="text-caption text-muted-foreground">
                        Aplica a
                    </small>
                    <b className="leading-snug font-semibold">
                        {tool.appliesTo}
                    </b>
                </p>
                <ul className="grid gap-3 px-1">
                    {tool.points.map((point) => (
                        <li
                            key={point}
                            className="grid grid-cols-[20px_minmax(0,1fr)] gap-2.5 text-meta leading-snug"
                        >
                            <Check
                                aria-hidden
                                className="mt-0.5 size-5 text-primary"
                            />
                            {point}
                        </li>
                    ))}
                </ul>
                <p className="mt-auto flex items-center gap-2.5 border-t pt-3 text-meta text-muted-foreground">
                    <span aria-hidden className="shrink-0">
                        <LandingHumi size={30} />
                    </span>
                    Humi te acompaña en esta herramienta.
                </p>
            </div>
        </div>
    );
}
