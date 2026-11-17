import { Scale } from 'lucide-react';
import {
    ICON_BOX,
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageNorm } from '@/types';

/** What the law asks, each rule with the article it comes from, and its key figures: when, to whom, the fine. */
export default function ToolPageNormSection({ norm }: { norm: ToolPageNorm }) {
    return (
        <section
            id="que-pide-la-norma"
            aria-labelledby="que-pide-la-norma-title"
            className={cn('scroll-mt-16 border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'grid gap-10')}>
                <div className="grid max-w-[760px] gap-4">
                    <h2 id="que-pide-la-norma-title" className={TITLE}>
                        {norm.title}
                    </h2>
                    <p className={LEAD}>{norm.lead}</p>
                </div>
                <dl className="grid gap-3 sm:grid-cols-3">
                    {norm.facts.map((fact) => (
                        <div
                            key={fact.label}
                            className="grid content-start gap-1.5 rounded-2xl border bg-card p-5"
                        >
                            <dt className="text-caption text-muted-foreground">
                                {fact.label}
                            </dt>
                            <dd className="text-lg leading-snug font-semibold text-pretty">
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <ul className="grid gap-4 lg:grid-cols-2">
                    {norm.rules.map((rule) => (
                        <li
                            key={rule.text}
                            className="grid grid-cols-[40px_minmax(0,1fr)] gap-4 rounded-2xl border bg-card p-5"
                        >
                            <span
                                aria-hidden
                                className={cn(ICON_BOX, 'size-10')}
                            >
                                <Scale className="size-5" />
                            </span>
                            <span className="grid gap-2">
                                <span className="leading-7 text-pretty">
                                    {rule.text}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    Fuente: {rule.source}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
