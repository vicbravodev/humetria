import { Badge } from '@/components/ui/badge';
import type { CaseRow } from '@/types';

/** Where a case stands: new, in review or closed, and late when the acknowledgement the company set itself is. */
export default function CaseStateBadge({
    status,
    label,
    overdue,
}: {
    status: CaseRow['status'];
    label: string;
    overdue: boolean;
}) {
    return (
        <span className="flex flex-wrap items-center gap-1.5">
            <Badge
                variant={
                    status === 'closed'
                        ? 'secondary'
                        : status === 'new'
                          ? 'default'
                          : 'outline'
                }
            >
                {label}
            </Badge>
            {overdue && <Badge variant="destructive">Acuse vencido</Badge>}
        </span>
    );
}
