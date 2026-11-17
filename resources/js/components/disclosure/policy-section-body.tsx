import { cn } from '@/lib/utils';
import type { PolicyTextSection } from '@/types';

const LIST_CLASS = {
    numbers: 'list-decimal',
    letters: 'list-[lower-alpha]',
} as const;

/** The words of one section of a policy: its paragraphs (split by a blank line) and its list, as plain text (criterio C-51). */
export default function PolicySectionBody({
    section,
}: {
    section: PolicyTextSection;
}) {
    return (
        <>
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
            {section.listStyle !== null && section.items.length > 0 && (
                <ol
                    className={cn(
                        'ml-6 grid gap-1.5 marker:text-muted-foreground',
                        LIST_CLASS[section.listStyle],
                    )}
                >
                    {section.items.map((item) => (
                        <li key={item} className="pl-1 text-pretty">
                            {item}
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}
