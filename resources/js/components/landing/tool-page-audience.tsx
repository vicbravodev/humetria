import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';
import type { ToolPageContent } from '@/types';

/** For whom: three readers of the same tool, each with what they get out of it. */
export default function ToolPageAudience({ page }: { page: ToolPageContent }) {
    return (
        <section
            id="para-quien"
            aria-labelledby="para-quien-title"
            className={cn(WRAP, 'grid scroll-mt-16 gap-9', SECTION)}
        >
            <Reveal>
                <h2 id="para-quien-title" className={TITLE}>
                    Para quién es
                </h2>
            </Reveal>
            <ul className="grid gap-10 md:grid-cols-3">
                {page.audience.map((reader, index) => (
                    <li
                        key={reader.who}
                        data-reveal="idle"
                        className={cn(
                            'grid content-start gap-2 border-t-2 border-family pt-4 reveal-rise',
                            `enter-delay-${index * 80}`,
                        )}
                    >
                        <p className="text-meta font-semibold text-family">
                            {reader.who}
                        </p>
                        <b className="text-xl leading-snug">{reader.title}</b>
                        <p className="leading-relaxed text-muted-foreground">
                            {reader.text}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
