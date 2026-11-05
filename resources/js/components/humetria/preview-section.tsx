import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { paragraphs } from '@/lib/question-flow';
import type { FlowSection } from '@/lib/question-flow';

/**
 * One section of a document's preview: its heading and paragraphs, or grey lines while its question waits. The section of
 * the question on screen is lit.
 */
export default function PreviewSection({
    section,
    current,
    children,
}: {
    section: FlowSection;
    current: boolean;
    /** What goes under the text (the way to rewrite it, on the review). */
    children?: ReactNode;
}) {
    return (
        <div
            className={cn(
                '-mx-3 flex flex-col gap-1 rounded-lg px-3 py-2.5 transition-colors duration-200 motion-reduce:transition-none',
                current && 'bg-accent',
            )}
        >
            <p className="font-semibold">{section.heading}</p>
            {section.text === null ? (
                <div aria-hidden className="flex flex-col gap-2 pt-1.5">
                    <span className="h-2.5 w-11/12 rounded-full bg-track" />
                    <span className="h-2.5 w-2/3 rounded-full bg-track" />
                </div>
            ) : (
                paragraphs(section.text).map((paragraph) => (
                    <p key={paragraph}>{paragraph}</p>
                ))
            )}
            {children}
        </div>
    );
}
