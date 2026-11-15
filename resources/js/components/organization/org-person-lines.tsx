import { countOf } from '@/lib/plural';
import { cn } from '@/lib/utils';
import type { OrgNode } from '@/types';

/** What a card says of a person: their name, their job and, when they lead people, how many. */
export default function OrgPersonLines({
    node,
    amber,
}: {
    node: OrgNode;
    /** The card asks for attention: its text is the warning color. */
    amber: boolean;
}) {
    return (
        <span className="flex min-w-0 flex-1 flex-col gap-0.5">
            <span className="truncate text-sm leading-5 font-semibold">
                {node.name}
            </span>
            <span className="flex min-w-0 items-center gap-1.5">
                <span
                    className={cn(
                        'truncate text-xs leading-4',
                        amber
                            ? 'text-warning-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    {node.job_title}
                </span>
                {node.direct_reports > 0 && (
                    <span
                        aria-hidden
                        className="shrink-0 rounded-full bg-secondary px-1.5 text-caption leading-4 text-secondary-foreground"
                    >
                        {countOf(node.direct_reports, 'directo', 'directos')}
                    </span>
                )}
            </span>
        </span>
    );
}
