import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { inviteMissingSummary } from '@/lib/campaigns';
import { inviteMissing } from '@/routes/tenant/campaigns';
import type { InviteMissingCount } from '@/types';

/**
 * Brings the evaluation up to date with the work center's people (criterio C-24), opened from the page's menu: it says
 * who it brings up to date and how many get a link, before any goes out. With nobody to invite there is nothing to confirm.
 */
export default function InviteMissingDialog({
    route,
    missing,
    open,
    onOpenChange,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    /** Who it brings up to date, and who of them gets a link. */
    missing: InviteMissingCount;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="¿Invitar a quien falte?"
            description="Reciben su enlace quienes entraron al centro de trabajo después del lanzamiento, quienes volvieron y quienes no lo recibieron por no tener correo. A los demás no les llega nada."
            confirmLabel="Invitar"
            destructive={false}
            disabled={missing.people === 0}
            form={inviteMissing.form(route)}
            preserveScroll
        >
            <p className="text-sm font-medium">
                {inviteMissingSummary(missing)}
            </p>
        </ConfirmDialog>
    );
}
