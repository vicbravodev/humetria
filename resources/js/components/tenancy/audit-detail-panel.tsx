import AuditDetailBody from '@/components/tenancy/audit-detail-body';
import { Badge } from '@/components/ui/badge';
import type { AuditTag } from '@/lib/audit-view';
import type { AuditEntryRow } from '@/types';

/** «Detalle del evento» docked beside the trail on a wide screen (tablero Bitácora): the entry chosen, always in sight. */
export default function AuditDetailPanel({
    entry,
    tag,
}: {
    entry: AuditEntryRow;
    tag: AuditTag;
}) {
    return (
        <aside
            aria-labelledby="audit-detail"
            className="sticky top-6 w-[320px] shrink-0 card"
        >
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2 id="audit-detail" className="flex-1 font-semibold">
                    Detalle del evento
                </h2>
                <Badge variant={tag.variant}>{tag.label}</Badge>
            </div>
            <AuditDetailBody entry={entry} />
        </aside>
    );
}
