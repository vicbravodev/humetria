import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import GlideChips from '@/components/humetria/glide-chips';
import ResourcesMenu from '@/components/landing/resources-menu';
import type { ResourceLink } from '@/types/public-nav';
import ToolsMenu from '@/components/landing/tools-menu';
import { useActiveSection } from '@/hooks/use-active-section';
import { PUBLIC_LINKS } from '@/lib/public-nav';
import { cn } from '@/lib/utils';

// The links come from one file that the Blade header of the blog reads too. A section jumps to its heading (`id`) and is
// marked while its whole section (`section`) is being read; a page is a plain link, marked on its own page. «Recursos»
// is a group: Blog, Roadmap, the guide, Confidencialidad and Humi live inside it so the bar keeps to four things.
const links = PUBLIC_LINKS;
const resourceItems = links.flatMap((link) => link.items ?? []);

// The hero comes first so nothing is marked until the page reaches a section.
const watched = [
    'inicio',
    ...[...links, ...resourceItems].flatMap((link) =>
        link.section === undefined ? [] : [link.section],
    ),
];

const ITEM =
    'relative flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 before:content-[""]';

/**
 * Four things in one pill: Herramientas (the tools menu), Cómo funciona, Precios and Recursos (a menu); a chip slides under
 * the one being read. Screens of 1,024 px and up only: below that the menu button takes the nav's place. `sectionBase` is the
 * page the sections live on, for a page that is not the landing.
 */
export default function HeaderNav({
    sectionBase = '',
}: {
    sectionBase?: string;
}) {
    const active = useActiveSection(watched);
    const { url } = usePage();
    const [hovered, setHovered] = useState<string | null>(null);
    const path = url.split('?')[0] ?? '';
    const hrefOf = (link: Pick<ResourceLink, 'id' | 'kind' | 'path'>) =>
        link.kind === 'page' ? (link.path ?? '') : `${sectionBase}#${link.id}`;
    const currentItem =
        resourceItems.find((item) =>
            item.kind === 'page'
                ? item.path !== undefined &&
                  (path === item.path || path.startsWith(`${item.path}/`))
                : item.section === active,
        )?.id ?? null;

    return (
        <nav aria-label="Secciones" className="hidden lg:block">
            <ul
                onMouseLeave={() => setHovered(null)}
                className="flex items-center rounded-full border bg-muted/70 p-1 backdrop-blur-md"
            >
                {links.map((link) => {
                    const current =
                        link.kind === 'group'
                            ? currentItem !== null
                            : active === link.section;
                    const tone = current
                        ? 'text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground';
                    const pointer = {
                        onMouseEnter: () => setHovered(link.id),
                        onFocus: () => setHovered(link.id),
                        onBlur: () => setHovered(null),
                    };

                    return (
                        <li key={link.id} className="relative">
                            <GlideChips
                                group="header"
                                current={current}
                                hovered={hovered === link.id}
                                className="rounded-full"
                            />
                            {link.kind === 'group' ? (
                                <div {...pointer}>
                                    <ResourcesMenu
                                        label={link.label}
                                        test={link.test}
                                        items={link.items ?? []}
                                        hrefOf={hrefOf}
                                        currentId={currentItem}
                                        triggerClassName={cn(ITEM, tone)}
                                    />
                                </div>
                            ) : link.id === 'herramientas' ? (
                                <div {...pointer} data-test={link.test}>
                                    <ToolsMenu
                                        variant="bar"
                                        triggerClassName={cn(ITEM, tone)}
                                    />
                                </div>
                            ) : (
                                <a
                                    href={hrefOf(link)}
                                    data-test={link.test}
                                    data-track="nav_click"
                                    data-track-target={link.id}
                                    aria-current={current ? 'true' : undefined}
                                    {...pointer}
                                    className={cn(ITEM, tone)}
                                >
                                    {link.label}
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
