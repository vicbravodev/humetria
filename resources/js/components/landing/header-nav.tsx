import { useState } from 'react';
import GlideChips from '@/components/humetria/glide-chips';
import ToolsMenu from '@/components/landing/tools-menu';
import { useActiveSection } from '@/hooks/use-active-section';
import { cn } from '@/lib/utils';

// Each link jumps to its heading (`id`) and is marked while its whole section (`section`) is being read.
const sections = [
    {
        id: 'como-funciona',
        section: 'seccion-como-funciona',
        label: 'Cómo funciona',
        test: 'header-how',
    },
    {
        id: 'herramientas',
        section: 'herramientas',
        label: 'Herramientas',
        test: 'header-tools',
    },
    {
        id: 'confidencialidad',
        section: 'seccion-confidencialidad',
        label: 'Confidencialidad',
        test: 'header-privacy',
    },
    { id: 'humi', section: 'seccion-humi', label: 'Humi', test: 'header-humi' },
    {
        id: 'precios',
        section: 'seccion-precios',
        label: 'Precios',
        test: 'header-pricing',
    },
] as const;

// The hero comes first so nothing is marked until the page reaches a section.
const watched = ['inicio', ...sections.map((section) => section.section)];

const ITEM =
    'relative flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 before:content-[""]';

/**
 * The sections of the landing in one pill: a chip slides under the one being read. «Herramientas» opens the tools menu.
 * Screens of 1,024 px and up only: below that the five sections, the theme, the sign in and the demo do not fit side by side, and the tools menu button takes the nav's place. `sectionBase` is the page the sections live on, for a page that is not the landing.
 */
export default function HeaderNav({
    sectionBase = '',
}: {
    sectionBase?: string;
}) {
    const active = useActiveSection(watched);
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <nav aria-label="Secciones" className="hidden lg:block">
            <ul
                onMouseLeave={() => setHovered(null)}
                className="flex items-center rounded-full border bg-muted/70 p-1 backdrop-blur-md"
            >
                {sections.map((section) => {
                    const current = active === section.section;
                    const tone = current
                        ? 'text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground';
                    const pointer = {
                        onMouseEnter: () => setHovered(section.id),
                        onFocus: () => setHovered(section.id),
                        onBlur: () => setHovered(null),
                    };

                    return (
                        <li key={section.id} className="relative">
                            <GlideChips
                                group="header"
                                current={current}
                                hovered={hovered === section.id}
                                className="rounded-full"
                            />
                            {section.id === 'herramientas' ? (
                                <div {...pointer} data-test={section.test}>
                                    <ToolsMenu
                                        variant="bar"
                                        triggerClassName={cn(ITEM, tone)}
                                    />
                                </div>
                            ) : (
                                <a
                                    href={`${sectionBase}#${section.id}`}
                                    data-test={section.test}
                                    data-track="nav_click"
                                    data-track-target={section.id}
                                    aria-current={current ? 'true' : undefined}
                                    {...pointer}
                                    className={cn(ITEM, tone)}
                                >
                                    {section.label}
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
