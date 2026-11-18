import { useState } from 'react';
import {
    DEFAULT_PEOPLE,
    PRICING_INTRO,
    PRICING_NOTES,
} from '@/components/landing/landing-pricing';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import PricingPeopleField from '@/components/landing/pricing-people-field';
import PricingTable from '@/components/landing/pricing-table';
import Reveal from '@/components/landing/reveal';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** What it costs, before asking for a demo: the headcount, then the plans side by side with what each includes. */
export default function PricingSection() {
    const [people, setPeople] = useState(DEFAULT_PEOPLE);

    return (
        <section
            id="seccion-precios"
            aria-labelledby="precios"
            className={SECTION}
        >
            <div className={cn(WRAP, 'flex flex-col gap-8 lg:gap-10')}>
                <div className="flex flex-col gap-4">
                    <h2 id="precios" className={cn(TITLE, 'scroll-mt-24')}>
                        Precios claros, desde $1,990 MXN al mes
                    </h2>
                    <p className={LEAD}>{PRICING_INTRO}</p>
                </div>
                <PricingPeopleField people={people} onChange={setPeople} />
                <Reveal amount={0.05}>
                    <PricingTable people={people} />
                </Reveal>
                <div className="flex flex-col gap-4 rounded-xl bg-accent px-[18px] py-4 md:flex-row md:items-center md:justify-between md:gap-8 md:px-6">
                    <ul
                        data-test="pricing-notes"
                        className="grid gap-1.5 text-meta"
                    >
                        {PRICING_NOTES.map((note) => (
                            <li key={note}>{note}</li>
                        ))}
                    </ul>
                    <Button
                        asChild
                        size="lg"
                        className="h-13 shrink-0 px-6 text-base"
                    >
                        <a
                            data-track="cta_click"
                            data-track-target="pricing_demo"
                            href="#agenda-una-demo"
                            data-test="pricing-demo"
                        >
                            Agenda una demo
                        </a>
                    </Button>
                </div>
            </div>
        </section>
    );
}
