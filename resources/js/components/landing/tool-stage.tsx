import { m } from 'motion/react';
import { Check } from 'lucide-react';
import LandingHumi from '@/components/landing/landing-humi';
import { EASE_OUT } from '@/components/landing/landing-motion';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import type { LandingTool } from '@/components/landing/landing-tools';

/** The tool picked: its film large, and beside it whom it applies to, what it does and Humi with it. */
export default function ToolStage({ tool }: { tool: LandingTool }) {
    return (
        <div
            id="tool-panel"
            role="tabpanel"
            data-test="tool-panel"
            aria-labelledby={`tool-tab-${tool.key}`}
            className="grid min-w-0 gap-5 rounded-3xl border bg-card p-3.5 shadow-raised sm:p-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6"
        >
            <ToolFilmPlayer key={tool.key} film={tool.film} />
            <m.div
                key={`${tool.key}-details`}
                initial={{
                    opacity: 0,
                    transform: 'translateY(8px)',
                    filter: 'blur(2px)',
                }}
                animate={{
                    opacity: 1,
                    transform: 'translateY(0px)',
                    filter: 'blur(0px)',
                }}
                transition={{ duration: 0.3, ease: EASE_OUT }}
                className="flex min-w-0 flex-col gap-4"
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
            </m.div>
        </div>
    );
}
