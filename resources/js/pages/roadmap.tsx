import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import LandingFooter from '@/components/landing/landing-footer';
import PrivacyNoticeHeader from '@/components/landing/privacy-notice-header';
import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import RoadmapLaneSection from '@/components/landing/roadmap-lane';
import SeoHead from '@/components/landing/seo-head';
import { home } from '@/routes';
import type { Seo } from '@/types';
import { cn } from '@/lib/utils';

/** What Humetría plans to add, in lanes without dates: fixed text, nothing from the database. */
export default function Roadmap({ seo }: { seo: Seo }) {
    return (
        <>
            <SeoHead seo={seo} />
            <PrivacyNoticeHeader />
            <main className={cn(WRAP, SECTION)}>
                <div className="flex flex-col gap-4 pb-10 lg:pb-14">
                    <h1 className={TITLE}>Lo que viene en Humetría</h1>
                    <p className={LEAD}>
                        Lo que estamos construyendo para oficinas y servicios.
                        El orden puede cambiar según lo que nos pidan; ninguna
                        etapa es una fecha de entrega.
                    </p>
                </div>
                <div className="grid max-w-[880px] gap-10">
                    {ROADMAP_LANES.map((lane) => (
                        <RoadmapLaneSection key={lane.id} lane={lane} />
                    ))}
                </div>
            </main>
            <LandingFooter sectionBase={home().url} />
        </>
    );
}
