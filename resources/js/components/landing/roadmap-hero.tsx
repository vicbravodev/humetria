import { LEAD, TITLE, WRAP } from '@/components/landing/landing-layout';
import RoadmapLegend from '@/components/landing/roadmap-legend';
import { cn } from '@/lib/utils';

const RINGS = [120, 200, 290, 390, 500, 620] as const;

/** The title and what the page promises, and does not, beside how to read its stages: on the brand's color, as the covers of the blog. */
export default function RoadmapHero() {
    return (
        <div className={cn(WRAP, 'pt-6 pb-8 sm:pt-8 lg:pb-10')}>
            <div className="relative overflow-hidden rounded-3xl bg-primary px-6 py-10 text-primary-foreground sm:px-10 lg:px-14 lg:py-14">
                <svg
                    aria-hidden
                    viewBox="0 0 1200 630"
                    preserveAspectRatio="xMaxYMin slice"
                    className="pointer-events-none absolute inset-0 size-full"
                >
                    {RINGS.map((radius, index) => (
                        <circle
                            key={radius}
                            cx={1050}
                            cy={120}
                            r={radius}
                            fill="none"
                            stroke="currentColor"
                            strokeOpacity={0.2 - index * 0.025}
                            strokeWidth={2}
                        />
                    ))}
                </svg>
                <div className="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-center lg:gap-14">
                    <div className="grid gap-4">
                        <h1 className={TITLE}>Lo que viene en Humetría</h1>
                        <p className={cn(LEAD, 'text-primary-foreground')}>
                            Lo que estamos construyendo para oficinas y
                            servicios, empezando por evaluar a tu gente. El
                            orden puede cambiar según lo que nos pidan; ninguna
                            etapa es una fecha de entrega.
                        </p>
                    </div>
                    <div className="text-card-foreground">
                        <RoadmapLegend />
                    </div>
                </div>
            </div>
        </div>
    );
}
