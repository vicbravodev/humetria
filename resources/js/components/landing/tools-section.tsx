import { FAMILIES, familyTools } from '@/components/landing/landing-families';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import ToolCard from '@/components/landing/tool-card';
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

// On a wide screen a family takes one row, however many tools it has: no orphan card.
const COLUMNS: Record<number, string> = {
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
};

/** Nine tools as three families, each a card that leads to the tool's own page with its film, the law and the questions. */
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
                        className="grid scroll-mt-24 gap-4"
                    >
                        <h3 className="flex items-baseline gap-3 text-xl font-semibold">
                            {family.name}
                            <span className="text-meta font-normal text-muted-foreground">
                                {family.hint}
                            </span>
                        </h3>
                        <ul
                            className={cn(
                                'grid gap-3 sm:grid-cols-2',
                                COLUMNS[family.tools.length],
                            )}
                        >
                            {familyTools(family).map((tool) => (
                                <li key={tool.key}>
                                    <ToolCard
                                        tool={tool}
                                        thumb={THUMBS[tool.key] ?? ''}
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
