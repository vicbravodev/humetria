import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/**
 * One heading of the checklist, folded until asked for: its rows only exist while it is open, so a list of 122
 * questions with their controls stays light for the browser and for screen readers.
 */
export default function QuestionGroup({
    heading,
    count,
    defaultOpen,
    children,
}: {
    heading: string;
    count: number;
    defaultOpen: boolean;
    children: ReactNode;
}) {
    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className="overflow-hidden rounded-xl border bg-card"
        >
            <h3>
                <CollapsibleTrigger className="group flex min-h-11 w-full items-center gap-3 bg-muted px-5 py-2.5 text-left text-[13px] leading-[18px] font-medium text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset">
                    <span className="min-w-0 flex-1">{heading}</span>
                    <span className="shrink-0 font-normal">
                        {count} {count === 1 ? 'pregunta' : 'preguntas'}
                    </span>
                    <ChevronDown
                        aria-hidden
                        className="size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180"
                    />
                </CollapsibleTrigger>
            </h3>
            <CollapsibleContent>
                <ul className="divide-y">{children}</ul>
            </CollapsibleContent>
        </Collapsible>
    );
}
