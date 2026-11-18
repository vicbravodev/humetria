import { m } from 'motion/react';
import {
    FAMILIES,
    FAMILY_CLASS,
    familyTools,
} from '@/components/landing/landing-families';
import { EASE_OUT } from '@/components/landing/landing-motion';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { workday2027 } from '@/routes/guides';

type Props = {
    /** Under the header word (`true`) or over the page on a phone. */
    floating: boolean;
    /** Closes the menu when a link of this very page is chosen. */
    onPick: () => void;
};

/** The nine tools by family, each to its own page, the section that shows them all and the guide. */
export default function ToolsMenuPanel({ floating, onPick }: Props) {
    return (
        <m.nav
            id="tools-menu"
            aria-label="Herramientas"
            initial={{ opacity: 0, y: -6, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{
                opacity: 0,
                y: -4,
                scale: 0.98,
                transition: { duration: 0.15 },
            }}
            transition={{ duration: 0.22, ease: EASE_OUT }}
            className={cn(
                'z-20 grid origin-top gap-5 rounded-3xl border bg-card p-5 text-left shadow-raised',
                floating
                    ? 'absolute top-full left-1/2 mt-3 w-[min(880px,90vw)] -translate-x-1/2 sm:grid-cols-3'
                    : 'fixed inset-x-4 top-[76px] max-h-[calc(100dvh-96px)] overflow-y-auto',
            )}
        >
            {FAMILIES.map((family) => (
                <div
                    key={family.key}
                    className={cn(
                        'grid content-start gap-1',
                        FAMILY_CLASS[family.key],
                    )}
                >
                    <p className="flex items-center gap-2 px-2.5 pb-1 text-caption font-semibold text-family">
                        <family.icon aria-hidden className="size-4" />
                        {family.name}
                    </p>
                    {familyTools(family).map((tool) => (
                        <a
                            key={tool.key}
                            href={toolPageUrl(tool.key)}
                            className="grid min-h-11 gap-0.5 rounded-xl px-2.5 py-2 transition-colors duration-150 ease-out hover:bg-family-soft"
                        >
                            <span className="font-semibold">{tool.name}</span>
                            <span className="text-caption text-muted-foreground">
                                {tool.hint}
                            </span>
                        </a>
                    ))}
                </div>
            ))}
            <div className="flex flex-wrap gap-x-6 gap-y-1 border-t pt-3 sm:col-span-3">
                <a
                    href={`${home().url}#herramientas`}
                    onClick={onPick}
                    data-test="tools-menu-all"
                    className="inline-flex min-h-11 items-center rounded-xl px-2.5 font-semibold text-primary"
                >
                    Ver las nueve herramientas
                </a>
                <a
                    href={workday2027().url}
                    className="inline-flex min-h-11 items-center rounded-xl px-2.5 font-semibold text-primary"
                >
                    Guía: qué cambia con el registro de jornada en 2027
                </a>
            </div>
        </m.nav>
    );
}
