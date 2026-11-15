import { CalendarCheck, RotateCcw } from 'lucide-react';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { isRestriction } from '@/lib/tenant-restriction';
import { activationCopy } from '@/lib/tenant-status';
import type { TenantRow } from '@/types';

/** The first item of the "…" menu: set the term, or lift the restriction a company has (that one asks a reason). */
export default function TenantTermItem({
    tenant,
    onChoose,
}: {
    tenant: TenantRow;
    onChoose: (action: 'activate' | 'reactivate') => void;
}) {
    return isRestriction(tenant.status) ? (
        <DropdownMenuItem onSelect={() => onChoose('reactivate')}>
            <RotateCcw />
            Reactivar
        </DropdownMenuItem>
    ) : (
        <DropdownMenuItem onSelect={() => onChoose('activate')}>
            <CalendarCheck />
            {activationCopy(tenant.status).action}
        </DropdownMenuItem>
    );
}
