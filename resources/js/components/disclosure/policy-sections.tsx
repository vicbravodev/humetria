import PolicySectionBody from '@/components/disclosure/policy-section-body';
import { cn } from '@/lib/utils';
import type { PolicyTextSection } from '@/types';

/**
 * The words of a policy, as plain text: the paragraphs (split by a blank line) and the items of each section. The one piece
 * that paints it in the panel and in the public page; React escapes the text, so nothing in it can become markup (criterio
 * C-51). The person reads it folded, in `PolicyAccordion`.
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
                        <PolicySectionBody section={section} />
                    </section>
                ))}
        </div>
    );
}
