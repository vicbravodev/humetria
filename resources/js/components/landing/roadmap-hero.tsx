import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

/** The title and what the page promises, and does not. */
export default function RoadmapHero() {
    return (
        <div className={cn(WRAP, SECTION, 'pb-10 sm:pb-10 lg:pb-12')}>
            <div className="grid gap-5">
                <h1 className={TITLE}>Lo que viene en Humetría</h1>
                <p className={LEAD}>
                    Lo que estamos construyendo para oficinas y servicios,
                    empezando por evaluar a tu gente. El orden puede cambiar
                    según lo que nos pidan; ninguna etapa es una fecha de
                    entrega.
                </p>
            </div>
        </div>
    );
}
