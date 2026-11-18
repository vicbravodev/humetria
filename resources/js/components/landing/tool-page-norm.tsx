import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import { workday2027 } from '@/routes/guides';
import type { ToolPageNorm } from '@/types';

type Props = { norm: ToolPageNorm; guideLink: boolean };

/** What the law asks: its figures in one row, each rule with the article it comes from, and what the law leaves open. */
export default function ToolPageNormSection({ norm, guideLink }: Props) {
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
                <dl className="grid border-t sm:grid-cols-3">
                    {norm.facts.map((fact) => (
                        <div
                            key={fact.label}
                            className="grid content-start gap-1 border-b py-5 sm:border-b-0 sm:pr-6 sm:not-first:pl-6 sm:not-last:border-r"
                        >
                            <dt className="text-meta text-muted-foreground">
                                {fact.label}
                            </dt>
                            <dd className="text-2xl leading-tight font-semibold tracking-[-0.015em]">
                                {fact.value}
                            </dd>
                            <dd className="text-caption text-muted-foreground">
                                {fact.note}
                            </dd>
                        </div>
                    ))}
                </dl>
                <ul className="grid gap-x-12 lg:grid-cols-2">
                    {norm.rules.map((rule) => (
                        <li
                            key={rule.text}
                            className="grid gap-1.5 border-t py-4"
                        >
                            <span className="text-lg leading-relaxed text-pretty">
                                {rule.text}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {rule.source}
                            </span>
                        </li>
                    ))}
                </ul>
                {norm.note === null ? null : (
                    <p className="max-w-[75ch] text-meta leading-relaxed text-muted-foreground">
                        {norm.note}
                    </p>
                )}
                {guideLink && (
                    <a
                        href={workday2027().url}
                        className="inline-flex min-h-11 items-center justify-self-start font-semibold text-primary"
                    >
                        Lee la guía: qué cambia en 2027
                    </a>
                )}
            </div>
        </section>
    );
}
