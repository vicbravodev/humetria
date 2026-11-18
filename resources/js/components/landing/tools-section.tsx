import {
    FAMILIES,
    FAMILY_CLASS,
    familyTools,
} from '@/components/landing/landing-families';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import ToolRow from '@/components/landing/tool-row';
import { cn } from '@/lib/utils';

/** The screen each card shows, from the demo company (public/images/tools, one file per theme). */
const THUMBS: Record<string, string> = {
    nom_035: 'nom-035-03-results',
    nom_037: 'nom-037-02-center',
    ley_silla: 'ley-silla-02-center',
    attendance: 'attendance-02-people',
    complaints: 'complaints-01-index',
    engagement: 'engagement-03-climate-groups',
    leave: 'leave-02-calendar',
    org_chart: 'org-chart-01-chart',
    compensation: 'compensation-03-compare',
};

// A family takes one row on a wide screen, however many tools it has: no orphan, and the fewer they are the larger their screens.
const COLUMNS: Record<number, string> = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
};

/** Nine tools as three families: the family's name and what it is for, then its tools as captioned screens, each leading to its own page. */
export default function ToolsSection() {
    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className={cn('scroll-mt-16 border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'flex flex-col gap-10')}>
                <Reveal className="flex max-w-[760px] flex-col gap-4">
                    <h2 id="herramientas-title" className={TITLE}>
                        Nueve herramientas, un solo lugar
                    </h2>
                    <p className={LEAD}>
                        Cada una con su página: qué pide la norma, cómo funciona
                        y un video de la herramienta. Humi te acompaña en todas.
                    </p>
                </Reveal>
                {FAMILIES.map((family) => (
                    <div
                        key={family.key}
                        id={`familia-${family.key}`}
                        data-test={`family-${family.key}`}
                        className={cn(
                            'flex scroll-mt-24 flex-col gap-2 border-t pt-8',
                            FAMILY_CLASS[family.key],
                        )}
                    >
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4">
                            <h3 className="text-2xl leading-tight font-semibold tracking-[-0.02em] text-family">
                                {family.name}
                            </h3>
                            <p className="text-meta text-muted-foreground">
                                {family.hint}
                            </p>
                        </div>
                        <ul
                            className={cn(
                                'divide-y border-b md:grid md:gap-x-6 md:gap-y-10 md:divide-y-0 md:border-b-0 md:pt-4',
                                COLUMNS[family.tools.length],
                            )}
                        >
                            {familyTools(family).map((tool, index) => (
                                <li key={tool.key}>
                                    <ToolRow
                                        tool={tool}
                                        thumb={THUMBS[tool.key] ?? ''}
                                        index={index}
                                    />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
