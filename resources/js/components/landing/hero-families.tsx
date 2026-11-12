import { ArrowRight } from 'lucide-react';
import { ICON_BOX } from '@/components/landing/landing-layout';
import { FAMILIES } from '@/components/landing/landing-families';
import { TOOLS } from '@/components/landing/landing-tools';

const toolName = (key: string): string =>
    TOOLS.find((tool) => tool.key === key)?.name ?? key;

/** The nine tools as three families: each link opens the tools section on the family's first tool. */
export default function HeroFamilies() {
    return (
        <nav aria-label="Herramientas por familia" data-test="hero-families">
            <ul className="grid border-t lg:grid-cols-3 lg:gap-x-10">
                {FAMILIES.map((family) => (
                    <li key={family.key} className="border-b">
                        <a
                            href={`#herramienta-${family.tools[0]}`}
                            data-test={`hero-family-${family.key}`}
                            onClick={(event) => {
                                event.preventDefault();
                                // Like the tool tabs, it rewrites the address instead of navigating: a navigation would let the router reset the scroll.
                                window.history.replaceState(
                                    null,
                                    '',
                                    `#herramienta-${family.tools[0]}`,
                                );
                                window.dispatchEvent(
                                    new HashChangeEvent('hashchange'),
                                );
                                document
                                    .getElementById('herramientas')
                                    ?.scrollIntoView();
                            }}
                            className="group grid min-h-18 grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-x-3.5 gap-y-1 py-3.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <span className={`${ICON_BOX} row-span-2 size-11`}>
                                <family.icon
                                    aria-hidden
                                    className="size-[22px]"
                                />
                            </span>
                            <b className="text-[17px] leading-tight font-semibold transition-colors group-hover:text-primary">
                                {family.name}
                            </b>
                            <span className="row-span-2 flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                                {family.tools.length}
                                <ArrowRight
                                    aria-hidden
                                    className="size-4 transition-transform duration-200 ease-out group-hover:translate-x-0.5"
                                />
                            </span>
                            <small className="col-start-2 text-sm leading-snug text-muted-foreground">
                                {family.tools.map(toolName).join(' · ')}
                            </small>
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
