import { FAMILIES, familyTools } from '@/components/landing/landing-families';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';
import { workday2027 } from '@/routes/guides';

/** The nine tools by family, each to its own page, and the guide: under the header word, or over the page on a phone. */
export default function ToolsMenuPanel({ floating }: { floating: boolean }) {
    return (
        <nav
            id="tools-menu"
            aria-label="Herramientas"
            className={cn(
                'z-20 grid gap-5 rounded-3xl border bg-card p-5 shadow-raised',
                floating
                    ? 'absolute top-full left-1/2 mt-2 w-[min(880px,90vw)] -translate-x-1/2 sm:grid-cols-3'
                    : 'fixed inset-x-4 top-[76px] max-h-[calc(100dvh-96px)] overflow-y-auto',
            )}
        >
            {FAMILIES.map((family) => (
                <div key={family.key} className="grid content-start gap-1">
                    <p className="px-2.5 pb-1 text-caption text-muted-foreground">
                        {family.name}
                    </p>
                    {familyTools(family).map((tool) => (
                        <a
                            key={tool.key}
                            href={toolPageUrl(tool.key)}
                            className="grid min-h-11 gap-0.5 rounded-xl px-2.5 py-2 hover:bg-muted"
                        >
                            <span className="font-semibold">{tool.name}</span>
                            <span className="text-caption text-muted-foreground">
                                {tool.hint}
                            </span>
                        </a>
                    ))}
                </div>
            ))}
            <a
                href={workday2027().url}
                className="inline-flex min-h-11 items-center rounded-xl px-2.5 font-semibold text-primary sm:col-span-3"
            >
                Guía: qué cambia con el registro de jornada en 2027
            </a>
        </nav>
    );
}
