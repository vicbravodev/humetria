import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import GlideChips from '@/components/humetria/glide-chips';
import ToolsMenu from '@/components/landing/tools-menu';
import { useActiveSection } from '@/hooks/use-active-section';
import publicNav from '@/lib/public-nav.json';
import { cn } from '@/lib/utils';

// The links come from one file that the Blade header of the blog reads too. A section jumps to its heading (`id`) and is
// marked while its whole section (`section`) is being read; a page (Blog, Roadmap) is a plain link and is marked on its own page.
const links = publicNav;
const sections = links.filter((link) => link.kind === 'section');

// The hero comes first so nothing is marked until the page reaches a section.
const watched = [
    'inicio',
    ...sections.flatMap((section) =>
        section.section === undefined ? [] : [section.section],
    ),
];

const ITEM =
    'relative flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 before:content-[""]';

/**
 * The sections of the landing, then Blog and Roadmap, in one pill: a chip slides under the one being read. «Herramientas» opens the tools menu.
 * Screens of 1,024 px and up only: below that the five sections, the theme, the sign in and the demo do not fit side by side, and the tools menu button takes the nav's place. `sectionBase` is the page the sections live on, for a page that is not the landing.
 */
export default function HeaderNav({
    sectionBase = '',
}: {
    sectionBase?: string;
}) {
    const active = useActiveSection(watched);
    const { url } = usePage();
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <nav aria-label="Secciones" className="hidden lg:block">
            <ul
                onMouseLeave={() => setHovered(null)}
                className="flex items-center rounded-full border bg-muted/70 p-1 backdrop-blur-md"
            >
                {links.map((section) => {
                    const current =
                        section.kind === 'page'
                            ? url.split('?')[0] === section.path
                            : active === section.section;
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
                                    href={
                                        section.kind === 'page'
                                            ? section.path
                                            : `${sectionBase}#${section.id}`
                                    }
                                    data-test={section.test}
                                    data-track="nav_click"
                                    data-track-target={section.id}
                                    aria-current={
                                        current
                                            ? section.kind === 'page'
                                                ? 'page'
                                                : 'true'
                                            : undefined
                                    }
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
