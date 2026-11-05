import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import PublicMeasures from '@/components/disclosure/public-measures';
import type { PublicBlockText, PublicMeasure } from '@/types';

/**
 * A block of 5.7 folded under its name (board V4-Pagina-Personas): its parts under their headings, then the measures of the
 * Programa the company chose to show, joined and never one in place of the other (criterio C-52).
 */
export default function PublicDetails({
    title,
    block,
    measures = [],
    children,
}: {
    title: string;
    block: PublicBlockText | null | undefined;
    measures?: PublicMeasure[];
    children?: ReactNode;
}) {
    if (
        (block ?? null) === null &&
        measures.length === 0 &&
        children === undefined
    ) {
        return null;
    }

    return (
        <details className="group card">
            <summary className="flex min-h-14 cursor-pointer list-none items-center gap-2 px-5 py-3 font-semibold [&::-webkit-details-marker]:hidden">
                <ChevronRight
                    aria-hidden
                    className="size-4 shrink-0 transition-transform group-open:rotate-90 motion-reduce:transition-none"
                />
                {title}
            </summary>
            <div className="flex flex-col gap-3 px-5 pb-5 text-sm">
                {block?.sections.map((section) => (
                    <div
                        key={`${section.heading ?? ''}${section.text}`}
                        className="flex flex-col gap-0.5"
                    >
                        {section.heading !== null && (
                            <p className="font-semibold">{section.heading}</p>
                        )}
                        <p className="whitespace-pre-line">{section.text}</p>
                    </div>
                ))}
                {measures.length > 0 && <PublicMeasures measures={measures} />}
                {children}
                {block !== null && block !== undefined && (
                    <p className="text-caption text-muted-foreground">
                        Publicado el {block.publishedOn}.
                    </p>
                )}
            </div>
        </details>
    );
}
