import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { GuideContent } from '@/types';

/** The guide's body: each section with its heading, its paragraphs and the articles it rests on. */
export default function GuideArticle({ guide }: { guide: GuideContent }) {
    return (
        <div className={cn(WRAP, 'grid max-w-[820px] gap-12 pb-16 lg:pb-20')}>
            {guide.sections.map((section, index) => (
                <section
                    key={section.heading}
                    id={`seccion-${index + 1}`}
                    aria-labelledby={`seccion-${index + 1}-title`}
                    className="grid scroll-mt-24 gap-4"
                >
                    <h2
                        id={`seccion-${index + 1}-title`}
                        className="text-2xl leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-3xl"
                    >
                        {section.heading}
                    </h2>
                    {section.paragraphs.map((paragraph) => (
                        <p
                            key={paragraph}
                            className="text-lg leading-8 text-pretty"
                        >
                            {paragraph}
                        </p>
                    ))}
                    <p className="text-caption text-muted-foreground">
                        Fuente: {section.sources.join(' · ')}
                    </p>
                </section>
            ))}
        </div>
    );
}
