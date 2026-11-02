import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { archive } from '@/routes/tenant/work-centers';
import type { WorkCenterRow } from '@/types';

/**
 * Asks for confirmation before taking a work center out of the list. Nothing is deleted; an evaluation in progress
 * keeps it, and the dialog says so.
 */
export default function ArchiveWorkCenterDialog({
    workCenter,
    open,
    onOpenChange,
}: {
    workCenter: WorkCenterRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`¿Archivar ${workCenter.name}?`}
            description="Dejará de aparecer en la lista. Su historia se conserva y puedes restaurarlo cuando quieras."
            confirmLabel="Archivar"
            form={archive.form({
                tenant: tenantSlug,
                workCenter: workCenter.id,
            })}
        />
    );
}
