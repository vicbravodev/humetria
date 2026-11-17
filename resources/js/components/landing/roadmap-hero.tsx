import { LEAD, TITLE, WRAP } from '@/components/landing/landing-layout';
import RoadmapLegend from '@/components/landing/roadmap-legend';
import { cn } from '@/lib/utils';

/** The title and what the page promises, and does not, beside how to read its stages. */
export default function RoadmapHero() {
    return (
        <div className={cn(WRAP, 'pt-10 pb-8 sm:pt-12 lg:pt-16 lg:pb-10')}>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-center lg:gap-14">
                <div className="grid gap-4">
                    <h1 className={TITLE}>Lo que viene en Humetría</h1>
                    <p className={LEAD}>
                        Lo que estamos construyendo para oficinas y servicios,
                        empezando por evaluar a tu gente. El orden puede cambiar
                        según lo que nos pidan; ninguna etapa es una fecha de
                        entrega.
                    </p>
                </div>
                <RoadmapLegend />
            </div>
        </div>
    );
}
