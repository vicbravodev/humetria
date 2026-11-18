import { ChevronDown } from 'lucide-react';
import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageFaq } from '@/types';

/** The questions people ask before a demo, each open on demand; the same ones the head declares as FAQPage. */
export default function ToolPageFaqSection({ faqs }: { faqs: ToolPageFaq[] }) {
    return (
        <section
            id="preguntas"
            aria-labelledby="preguntas-title"
            className={cn('scroll-mt-16 border-y bg-band', SECTION)}
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
                <div className="border-t">
                    {faqs.map((faq, index) => (
                        <details
                            key={faq.question}
                            open={index === 0}
                            className="group border-b"
                        >
                            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-lg leading-snug font-semibold [&::-webkit-details-marker]:hidden">
                                {faq.question}
                                <ChevronDown
                                    aria-hidden
                                    className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-180 motion-reduce:transition-none"
                                />
                            </summary>
                            <p className="max-w-[70ch] pb-5 leading-relaxed text-pretty text-muted-foreground">
                                {faq.answer}
                            </p>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
