import { cn } from '@/lib/utils';
import type { PolicyTextSection } from '@/types';

const LIST_CLASS = {
    numbers: 'list-decimal',
    letters: 'list-[lower-alpha]',
} as const;

/**
 * The words of a policy, as plain text: the paragraphs (split by a blank line) and the items of each section. The one piece
 * that paints it in the panel, in the personal page and in the public page; React escapes the text, so nothing in it can
 * become markup (criterio C-51).
 */
export default function PolicySections({
    sections,
    tone = 'panel',
}: {
    sections: PolicyTextSection[];
    /** "person" and "public" read larger, for a phone. */
    tone?: 'panel' | 'person';
}) {
    return (
        <div
            className={cn(
                'grid gap-5',
                tone === 'person' && 'text-[17px] leading-7',
            )}
        >
            {sections
                .filter(
                    (section) =>
                        section.text !== '' || section.items.length > 0,
                )
                .map((section) => (
                    <section
                        key={section.key}
                        aria-labelledby={`policy-${section.key}`}
                        className="grid gap-2"
                    >
                        <h3
                            id={`policy-${section.key}`}
                            className="text-base font-semibold"
                        >
                            {section.heading}
                        </h3>
                        {section.text
                            .split(/\n{2,}/)
                            .filter((paragraph) => paragraph !== '')
                            .map((paragraph) => (
                                <p
                                    key={paragraph}
                                    className="text-pretty whitespace-pre-line"
                                >
                                    {paragraph}
                                </p>
                            ))}
                        {section.listStyle !== null &&
                            section.items.length > 0 && (
                                <ol
                                    className={cn(
                                        'ml-6 grid gap-1.5 marker:text-muted-foreground',
                                        LIST_CLASS[section.listStyle],
                                    )}
                                >
                                    {section.items.map((item) => (
                                        <li
                                            key={item}
                                            className="pl-1 text-pretty"
                                        >
                                            {item}
                                        </li>
                                    ))}
                                </ol>
                            )}
                    </section>
                ))}
        </div>
    );
}
