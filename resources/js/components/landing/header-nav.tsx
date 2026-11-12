import { useState } from 'react';
import GlideChips from '@/components/humetria/glide-chips';
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
] as const;

// The hero comes first so nothing is marked until the page reaches a section.
const watched = ['inicio', ...sections.map((section) => section.section)];

/** The sections of the page in one pill: a chip slides under the one being read. Wide screens only. */
export default function HeaderNav() {
    const active = useActiveSection(watched);
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <nav aria-label="Secciones" className="hidden md:block">
            <ul
                onMouseLeave={() => setHovered(null)}
                className="flex items-center rounded-full border bg-muted/70 p-1 backdrop-blur-md"
            >
                {sections.map((section) => {
                    const current = active === section.section;

                    return (
                        <li key={section.id} className="relative">
                            <GlideChips
                                group="header"
                                current={current}
                                hovered={hovered === section.id}
                                className="rounded-full"
                            />
                            <a
                                href={`#${section.id}`}
                                data-test={section.test}
                                aria-current={current ? 'true' : undefined}
                                onMouseEnter={() => setHovered(section.id)}
                                onFocus={() => setHovered(section.id)}
                                onBlur={() => setHovered(null)}
                                className={cn(
                                    'relative flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 before:content-[""]',
                                    current
                                        ? 'text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {section.label}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
