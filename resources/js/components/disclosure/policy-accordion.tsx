import { ChevronRight } from 'lucide-react';
import PolicySectionBody from '@/components/disclosure/policy-section-body';
import type { PolicyTextSection } from '@/types';

/**
 * The whole policy for the person, a section at a time: the first one open, the rest one tap away. Nothing is summarized or
 * left out (it is the policy the company signed); native `details`, so the text is always in the page.
 */
export default function PolicyAccordion({
    sections,
}: {
    sections: PolicyTextSection[];
}) {
    return (
        <div className="card px-4 text-[17px] leading-7">
            {sections
                .filter(
                    (section) =>
                        section.text !== '' || section.items.length > 0,
                )
                .map((section, index) => (
                    <details
                        key={section.key}
                        open={index === 0}
                        className="group border-t first:border-t-0"
                    >
                        <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 rounded-md text-[17px] font-semibold focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                            {section.heading}
                            <ChevronRight
                                aria-hidden
                                className="ml-auto size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-90 motion-reduce:transition-none"
                            />
                        </summary>
                        <div className="grid gap-2 pb-4">
                            <PolicySectionBody section={section} />
                        </div>
                    </details>
                ))}
        </div>
    );
}
