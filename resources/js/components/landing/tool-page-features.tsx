import { WRAP } from '@/components/landing/landing-layout';
import ToolPageShot from '@/components/landing/tool-page-shot';
import { cn } from '@/lib/utils';
import type { ToolPageFeature } from '@/types';

/** What the tool does, one block per row, text and a real screenshot alternating sides. */
export default function ToolPageFeatures({
    features,
}: {
    features: ToolPageFeature[];
}) {
    return (
        <section
            aria-label="Lo que hace"
            className={cn(WRAP, 'grid gap-20 pt-14 pb-20 lg:gap-28 lg:pb-24')}
        >
            {features.map((feature, index) => (
                <div
                    key={feature.title}
                    className="grid gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-center lg:gap-14"
                >
                    <div className="grid content-start gap-4">
                        <p className="text-meta font-semibold text-primary">
                            {feature.kicker}
                        </p>
                        <h3 className="text-[26px] leading-tight font-semibold tracking-[-0.025em] text-balance sm:text-[32px]">
                            {feature.title}
                        </h3>
                        <p className="max-w-[60ch] text-lg leading-relaxed text-pretty text-muted-foreground">
                            {feature.text}
                        </p>
                        <ul className="grid list-disc gap-2 pl-5 leading-relaxed marker:text-primary">
                            {feature.bullets.map((bullet) => (
                                <li key={bullet}>{bullet}</li>
                            ))}
                        </ul>
                    </div>
                    <figure
                        className={cn(
                            'm-0 grid gap-3',
                            index % 2 === 1 && 'lg:-order-1',
                        )}
                    >
                        <ToolPageShot feature={feature} />
                        <figcaption className="text-meta leading-snug text-muted-foreground">
                            {feature.caption}
                        </figcaption>
                    </figure>
                </div>
            ))}
        </section>
    );
}
