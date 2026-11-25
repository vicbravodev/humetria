import { router } from '@inertiajs/react';
import OfflineMarkEmpty from '@/components/attendance/offline-mark-empty';
import OfflineMarkListItem from '@/components/attendance/offline-mark-list-item';
import OfflineMarkToolbar from '@/components/attendance/offline-mark-toolbar';
import BulkBar from '@/components/humetria/bulk-bar';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useOfflineSelection } from '@/hooks/use-offline-selection';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { selectionPayload } from '@/lib/attendance/offline-marks';
import { verifyMany } from '@/routes/tenant/attendance/offline';
import type { OfflineMarkRow, Paginated } from '@/types';

/**
 * The marks made without a connection that wait for RH, a page at a time, the one in view marked. Whoever resolves can
 * choose several (or the whole page) and verify them together, and leave only the ones with no flags to do it with those.
 */
export default function OfflineMarkList({
    marks,
    selectedId,
    q,
    flags,
    canResolve,
}: {
    marks: Paginated<OfflineMarkRow>;
    selectedId: string | null;
    q: string;
    flags: string;
    canResolve: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const selection = useOfflineSelection(marks.data.map((mark) => mark.id));

    if (marks.total === 0) {
        return <OfflineMarkEmpty q={q} unflagged={flags === 'none'} />;
    }

    return (
        <div className="flex flex-col gap-3">
            <OfflineMarkToolbar
                q={q}
                unflagged={flags === 'none'}
                canResolve={canResolve}
                all={selection.all}
                onToggleAll={selection.toggleAll}
            />
            <div className="overflow-hidden card">
                <ul aria-label="Marcas sin conexión" className="divide-y">
                    {marks.data.map((row) => (
                        <OfflineMarkListItem
                            key={row.id}
                            row={row}
                            selected={row.id === selectedId}
                            checked={selection.ids.includes(row.id)}
                            onCheck={(checked) =>
                                selection.toggle(row.id, checked)
                            }
                            canResolve={canResolve}
                            q={q}
                            flags={flags}
                            page={marks.current_page}
                        />
                    ))}
                </ul>
                <BulkBar
                    count={selection.ids.length}
                    one="marca elegida"
                    many="marcas elegidas"
                    onClear={selection.clear}
                >
                    <Button
                        onClick={() =>
                            router.post(
                                verifyMany.url(tenantSlug),
                                selectionPayload(selection.ids),
                                {
                                    preserveScroll: true,
                                    onSuccess: selection.clear,
                                },
                            )
                        }
                    >
                        Verificar seleccionadas
                    </Button>
                </BulkBar>
            </div>
            <Pagination
                page={marks}
                label="Páginas de marcas sin conexión"
                pinned={false}
            />
        </div>
    );
}
