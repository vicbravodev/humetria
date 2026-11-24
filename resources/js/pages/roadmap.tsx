import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import { useState } from 'react';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import { WRAP } from '@/components/landing/landing-layout';
import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import RoadmapCta from '@/components/landing/roadmap-cta';
import RoadmapFilters from '@/components/landing/roadmap-filters';
import RoadmapHero from '@/components/landing/roadmap-hero';
import RoadmapLaneSection from '@/components/landing/roadmap-lane';
import SeoHead from '@/components/landing/seo-head';
import { useScrollReveal } from '@/hooks/use-scroll-reveal';
import { cspNonce } from '@/lib/csp-nonce';
import { areaCounts, filterLanes } from '@/lib/roadmap';
import type { AreaFilter } from '@/lib/roadmap';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { Seo } from '@/types';

const COUNTS = areaCounts(ROADMAP_LANES);

/** What Humetría plans to add, in stages without dates and filterable by area: fixed text, nothing from the database. */
export default function Roadmap({ seo }: { seo: Seo }) {
    useScrollReveal();
    const [area, setArea] = useState<AreaFilter>('all');

    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                <SeoHead seo={seo} />
                <LandingHeader sectionBase={home().url} />
                <main>
                    <RoadmapHero />
                    <RoadmapFilters
                        area={area}
                        counts={COUNTS}
                        onChange={setArea}
                    />
                    <div className={cn(WRAP, 'grid gap-9 py-9 lg:py-12')}>
                        {filterLanes(ROADMAP_LANES, area).map((lane) => (
                            <RoadmapLaneSection key={lane.id} lane={lane} />
                        ))}
                    </div>
                    <RoadmapCta />
                </main>
                <LandingFooter sectionBase={home().url} />
            </MotionConfig>
        </LazyMotion>
    );
}
