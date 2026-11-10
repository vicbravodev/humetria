import { ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import OrgNodeBadges from '@/components/organization/org-node-badges';
import { placeText, teamText } from '@/lib/org-chart/labels';
import { cn } from '@/lib/utils';
import type { OrgNode } from '@/types';

/**
 * A person of the org chart: who they are, where they work and the size of their team, with what is worth a second
 * look. Whoever is only on the way to someone within the filters is dimmed; the focused person is outlined.
 */
export default function OrgNodeCard({
    node,
    focused,
    open,
    onToggle,
}: {
    node: OrgNode;
    focused: boolean;
    /** Whether their reports are on screen; undefined for someone who leads nobody. */
    open: boolean | undefined;
    onToggle: () => void;
}) {
    const place = placeText(node);

    return (
        <div
            data-slot="org-node"
            className={cn(
                'flex max-w-xl break-inside-avoid items-center gap-3 card p-3 md:px-4',
                focused && 'border-primary ring-1 ring-primary',
                !node.matches && 'border-dashed bg-transparent',
            )}
        >
            <Initials name={node.name} />
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <p
                    className={cn(
                        'truncate font-medium',
                        !node.matches && 'text-muted-foreground',
                    )}
                >
                    {node.name}
                </p>
                {[node.job_title, place]
                    .filter((line) => line !== '')
                    .map((line) => (
                        <p
                            key={line}
                            className="truncate text-caption text-muted-foreground"
                        >
                            {line}
                        </p>
                    ))}
                {node.direct_reports > 0 && (
                    <p className="text-caption text-muted-foreground">
                        {teamText(node)}
                    </p>
                )}
                <OrgNodeBadges node={node} />
            </div>
            {open !== undefined && (
                // The keyboard opens a person with the arrows, on the person: this button is for the pointer.
                <button
                    type="button"
                    tabIndex={-1}
                    aria-label={`${open ? 'Ocultar' : 'Ver'} los reportes de ${node.name}`}
                    onClick={onToggle}
                    className="flex size-11 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-150 hover:bg-accent hover:text-accent-foreground print:hidden"
                >
                    <ChevronRight
                        aria-hidden
                        className={cn(
                            'size-5 transition-transform duration-200 motion-reduce:transition-none',
                            open && 'rotate-90',
                        )}
                    />
                </button>
            )}
        </div>
    );
}
