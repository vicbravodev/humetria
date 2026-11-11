import { m } from 'motion/react';
import { Check } from 'lucide-react';
import LandingHumi from '@/components/landing/landing-humi';
import { ICON_BOX } from '@/components/landing/landing-layout';
import { TOOL_ICONS } from '@/components/landing/landing-families';
import { EASE_OUT } from '@/components/landing/landing-motion';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import type { LandingTool } from '@/components/landing/landing-tools';

/** The tool picked: its name, its film large, whom it applies to and what it does, and Humi beside it. */
export default function ToolStage({ tool }: { tool: LandingTool }) {
    const Icon = TOOL_ICONS[tool.key];

    return (
        <div
            id="tool-panel"
            role="tabpanel"
            data-test="tool-panel"
            aria-labelledby={`tool-tab-${tool.key}`}
            className="min-w-0 rounded-3xl border bg-card p-3.5 shadow-raised sm:p-5"
        >
            <m.div
                key={tool.key}
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
            >
                <div className="flex items-center gap-3 px-1 pt-1 pb-3">
                    <span className={`${ICON_BOX} size-11`}>
                        <Icon aria-hidden className="size-[22px]" />
                    </span>
                    <div>
                        <h3 className="text-xl leading-tight font-semibold tracking-[-0.02em]">
                            {tool.name}
                        </h3>
                        <p className="text-meta leading-snug text-muted-foreground">
                            {tool.hint}
                        </p>
                    </div>
                </div>
                <ToolFilmPlayer key={tool.key} film={tool.film} />
                <div className="mt-4 grid gap-4 lg:mt-4 lg:grid-cols-[minmax(0,200px)_minmax(0,1fr)] lg:gap-6">
                    <p className="grid content-start gap-1 rounded-xl bg-sidebar p-4">
                        <small className="text-caption text-muted-foreground">
                            Aplica a
                        </small>
                        <b className="leading-snug font-semibold">
                            {tool.appliesTo}
                        </b>
                    </p>
                    <ul className="grid gap-3 px-1 lg:grid-cols-2 lg:gap-x-6 lg:gap-y-3.5">
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
                </div>
            </m.div>
            <p className="mt-4 flex items-center gap-2.5 border-t pt-3 text-meta text-muted-foreground">
                <span aria-hidden className="shrink-0">
                    <LandingHumi size={30} />
                </span>
                Humi te acompaña en esta herramienta.
            </p>
        </div>
    );
}
