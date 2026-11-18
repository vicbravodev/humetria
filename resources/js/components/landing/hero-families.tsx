import { ArrowRight } from 'lucide-react';
import { FAMILIES, FAMILY_CLASS } from '@/components/landing/landing-families';
import { TOOLS } from '@/components/landing/landing-tools';

const toolName = (key: string): string =>
    TOOLS.find((tool) => tool.key === key)?.name ?? key;

/** The nine tools as three families: each link jumps to the family's cards in the tools section. */
export default function HeroFamilies() {
    return (
        <nav aria-label="Herramientas por familia" data-test="hero-families">
            <ul className="grid border-t lg:grid-cols-3 lg:gap-x-10">
                {FAMILIES.map((family) => (
                    <li
                        key={family.key}
                        className={`border-b ${FAMILY_CLASS[family.key]}`}
                    >
                        <a
                            href={`#familia-${family.key}`}
                            data-test={`hero-family-${family.key}`}
                            data-track="family_click"
                            data-track-target={family.key}
                            className="group grid grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-x-3.5 gap-y-1 py-3.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <span className="row-span-2 flex size-11 shrink-0 items-center justify-center rounded-xl bg-family-soft text-family">
                                <family.icon
                                    aria-hidden
                                    className="size-[22px]"
                                />
                            </span>
                            <b className="text-[17px] leading-tight font-semibold transition-colors group-hover:text-family">
                                {family.name}
                            </b>
                            <span className="row-span-2 flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                                {family.tools.length}
                                <ArrowRight
                                    aria-hidden
                                    className="size-4 transition-transform duration-200 ease-out group-hover:translate-x-0.5"
                                />
                            </span>
                            <small className="col-start-2 line-clamp-2 min-h-[2lh] text-sm leading-snug text-muted-foreground">
                                {family.tools.map(toolName).join(' · ')}
                            </small>
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
