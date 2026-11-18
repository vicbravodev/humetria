import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
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
            <h2 id="para-quien-title" className={TITLE}>
                Para quién es
            </h2>
            <ul className="grid gap-10 md:grid-cols-3">
                {page.audience.map((reader) => (
                    <li
                        key={reader.who}
                        className="grid content-start gap-2 border-t-2 border-foreground pt-4"
                    >
                        <p className="text-meta font-semibold text-primary">
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
