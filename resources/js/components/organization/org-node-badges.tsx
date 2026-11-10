import { Badge } from '@/components/ui/badge';
import type { OrgNode } from '@/types';

/** What is worth a second look in a person of the org chart: they left, their manager left, or the size of their team. */
export default function OrgNodeBadges({ node }: { node: OrgNode }) {
    return (
        <div className="flex flex-wrap gap-1.5 empty:hidden">
            {!node.active && <Badge variant="secondary">Dado de baja</Badge>}
            {node.manager_inactive && (
                <Badge variant="warning">Jefe dado de baja</Badge>
            )}
            {node.flag !== null && (
                <Badge variant="warning">{node.flag.label}</Badge>
            )}
        </div>
    );
}
