import { useState } from 'react';
import AuditDetailPanel from '@/components/tenancy/audit-detail-panel';
import AuditDetailSheet from '@/components/tenancy/audit-detail-sheet';
import AuditTable from '@/components/tenancy/audit-table';
import { useMediaQuery } from '@/hooks/use-media-query';
import { auditTag } from '@/lib/audit-view';
import type { AuditEntryRow, AuditFamily } from '@/types';

/**
 * The trail with the detail of one entry (tablero Bitácora): on a wide screen docked beside the table, the newest
 * entry until another is chosen; narrower, the detail opens as a sheet when an entry is chosen.
 */
export default function AuditWorkArea({
    entries,
    families,
}: {
    entries: AuditEntryRow[];
    families: AuditFamily[];
}) {
    const wide = useMediaQuery('(min-width: 1280px)');
    const [chosenId, setChosenId] = useState<string | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const chosen =
        entries.find((entry) => entry.id === chosenId) ??
        (wide ? entries[0] : undefined);

    return (
        <div className="flex items-start gap-6">
            <div className="min-w-0 flex-1">
                <AuditTable
                    entries={entries}
                    families={families}
                    chosen={wide ? (chosen?.id ?? null) : null}
                    onChoose={(entry) => {
                        setChosenId(entry.id);
                        setSheetOpen(!wide);
                    }}
                />
            </div>
            {wide && chosen !== undefined && (
                <AuditDetailPanel
                    entry={chosen}
                    tag={auditTag(chosen, families)}
                />
            )}
            {!wide && chosen !== undefined && (
                <AuditDetailSheet
                    entry={chosen}
                    tag={auditTag(chosen, families)}
                    open={sheetOpen}
                    onOpenChange={setSheetOpen}
                />
            )}
        </div>
    );
}
