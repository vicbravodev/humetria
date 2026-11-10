import OrgNodeCard from '@/components/organization/org-node-card';
import OrgTreeMore from '@/components/organization/org-tree-more';
import type { OrgTreeView } from '@/hooks/use-org-tree';
import { orgNodeId } from '@/hooks/use-org-tree';
import type { OrgNode } from '@/types';

/** Where a person sits among their siblings, as a screen reader announces it. */
type Position = { level: number; index: number; size: number };

/**
 * A person of the tree and, when open, their reports: the ones loaded so far, then what is still on its way, the way
 * to try again when it failed, or «Ver más» for the next page.
 */
export default function OrgTreeNode({
    node,
    position,
    tree,
    view,
}: {
    node: OrgNode;
    position: Position;
    tree: OrgTreeView;
    view: { focusId: string | null; filtered: boolean };
}) {
    const branch = tree.state.children.get(node.id);
    const open = tree.expandable(node)
        ? tree.state.expanded.has(node.id)
        : undefined;
    const reports = branch?.nodes ?? [];

    return (
        <li
            id={orgNodeId(node.id)}
            role="treeitem"
            aria-label={[node.name, node.job_title]
                .filter((part) => part !== '')
                .join(', ')}
            aria-expanded={open}
            aria-level={position.level}
            aria-setsize={position.size}
            aria-posinset={position.index + 1}
            aria-current={node.id === view.focusId || undefined}
            tabIndex={tree.active === node.id ? 0 : -1}
            onFocus={(event) => {
                if (event.target === event.currentTarget) {
                    tree.setActive(node.id);
                }
            }}
            className="outline-none [&:focus-visible>[data-slot=org-node]]:outline-2 [&:focus-visible>[data-slot=org-node]]:outline-offset-2 [&:focus-visible>[data-slot=org-node]]:outline-ring"
        >
            <OrgNodeCard
                node={node}
                focused={node.id === view.focusId}
                open={open}
                onToggle={() => tree.toggle(node.id)}
            />
            {open === true && branch !== undefined && (
                <ul role="group" className="flex flex-col gap-3 pt-3">
                    {reports.map((report, index) => (
                        <OrgTreeNode
                            key={report.id}
                            node={report}
                            position={{
                                level: position.level + 1,
                                index,
                                size: Math.max(
                                    reports.length,
                                    view.filtered ? 0 : node.direct_reports,
                                ),
                            }}
                            tree={tree}
                            view={view}
                        />
                    ))}
                    <OrgTreeMore
                        manager={node}
                        branch={branch}
                        filtered={view.filtered}
                        onLoad={() => tree.loadMore(node.id)}
                    />
                </ul>
            )}
        </li>
    );
}
