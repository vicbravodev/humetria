import { Check } from 'lucide-react';
import {
    ICON_BOX,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageFeature } from '@/types';

/** How Humetría solves it: three to five blocks. */
export default function ToolPageFeatures({
    features,
}: {
    features: ToolPageFeature[];
}) {
    return (
        <section
            id="como-funciona"
            aria-labelledby="como-funciona-title"
            className={cn('scroll-mt-16', SECTION)}
        >
            <div className={cn(WRAP, 'grid gap-10')}>
                <h2 id="como-funciona-title" className={TITLE}>
                    Cómo lo resuelve Humetría
                </h2>
                <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <li
                            key={feature.title}
                            className="grid content-start gap-3 rounded-2xl border bg-card p-6 shadow-raised"
                        >
                            <span
                                aria-hidden
                                className={cn(ICON_BOX, 'size-10')}
                            >
                                <Check className="size-5" />
                            </span>
                            <h3 className="text-lg leading-snug font-semibold">
                                {feature.title}
                            </h3>
                            <p className="leading-7 text-pretty text-muted-foreground">
                                {feature.text}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
