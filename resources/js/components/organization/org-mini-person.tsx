import { ArrowUp, ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { teamText } from '@/lib/org-chart/labels';
import type { OrgNode } from '@/types';

/** A person as a small clickable row: in the panel, their manager and the people of their team; on a phone, the same. */
export default function OrgMiniPerson({
    node,
    onSelect,
    direction = 'down',
}: {
    node: OrgNode;
    onSelect: (id: string) => void;
    /** `up` marks the manager with an arrow. */
    direction?: 'up' | 'down';
}) {
    return (
        <button
            type="button"
            onClick={() => onSelect(node.id)}
            data-slot="org-mini-person"
            className="flex min-h-14 w-full items-center gap-3 rounded-xl border bg-card px-3 py-2 text-left transition-colors duration-150 hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <Initials name={node.name} />
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-semibold">
                    {node.name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {[
                        node.job_title,
                        node.direct_reports > 0 ? teamText(node) : '',
                    ]
                        .filter((part) => part !== '')
                        .join(' · ')}
                </span>
            </span>
            {direction === 'up' ? (
                <ArrowUp aria-hidden className="size-4 text-primary" />
            ) : (
                <ChevronRight aria-hidden className="size-4 text-primary" />
            )}
        </button>
    );
}
