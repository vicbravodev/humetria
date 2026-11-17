import { ChevronDown } from 'lucide-react';
import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageFaq } from '@/types';

/** The questions people ask before a demo, each one open on demand; the same ones the head declares as FAQPage. */
export default function ToolPageFaqSection({ faqs }: { faqs: ToolPageFaq[] }) {
    return (
        <section
            id="preguntas"
            aria-labelledby="preguntas-title"
            className={cn('scroll-mt-16', SECTION)}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-14',
                )}
            >
                <h2 id="preguntas-title" className={TITLE}>
                    Preguntas frecuentes
                </h2>
                <div className="grid gap-3">
                    {faqs.map((faq) => (
                        <details
                            key={faq.question}
                            className="group rounded-2xl border bg-card"
                        >
                            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-5 py-4 text-lg leading-snug font-semibold [&::-webkit-details-marker]:hidden">
                                {faq.question}
                                <ChevronDown
                                    aria-hidden
                                    className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-180 motion-reduce:transition-none"
                                />
                            </summary>
                            <p className="px-5 pb-5 leading-7 text-pretty text-muted-foreground">
                                {faq.answer}
                            </p>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
