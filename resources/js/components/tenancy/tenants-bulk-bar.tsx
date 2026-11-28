import { CalendarPlus, UserCog } from 'lucide-react';
import { useState } from 'react';
import BulkBar from '@/components/humetria/bulk-bar';
import AssignManagerDialog from '@/components/tenancy/assign-manager-dialog';
import ExtendTrialDialog from '@/components/tenancy/extend-trial-dialog';
import { Button } from '@/components/ui/button';
import type { IdSelection } from '@/hooks/use-id-selection';
import type { StaffMember } from '@/types';

/** What the superadmin does at once with the companies chosen on this page: hand them to someone, or give more trial. */
export default function TenantsBulkBar({
    selection,
    staff,
}: {
    selection: IdSelection;
    staff: StaffMember[];
}) {
    const [open, setOpen] = useState<'manager' | 'trial' | null>(null);
    const dialog = (which: 'manager' | 'trial') => ({
        companies: selection.chosen,
        open: open === which,
        onOpenChange: (isOpen: boolean) => setOpen(isOpen ? which : null),
    });

    return (
        <>
            <BulkBar count={selection.chosen.length} onClear={selection.clear}>
                <Button size="sm" onClick={() => setOpen('manager')}>
                    <UserCog aria-hidden />
                    Asignar responsable
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setOpen('trial')}
                >
                    <CalendarPlus aria-hidden />
                    Extender prueba
                </Button>
            </BulkBar>
            <AssignManagerDialog staff={staff} {...dialog('manager')} />
            <ExtendTrialDialog {...dialog('trial')} />
        </>
    );
}
