import { Checkbox } from '@/components/ui/checkbox';
import { TableCell } from '@/components/ui/table';
import type { IdSelection } from '@/hooks/use-id-selection';
import type { TenantRow } from '@/types';

/** The box that chooses a company to act on several at once; nothing for whoever cannot. */
export default function TenantSelectCell({
    tenant,
    selection,
}: {
    tenant: TenantRow;
    selection: IdSelection | null;
}) {
    if (selection === null) {
        return null;
    }

    return (
        <TableCell className="w-px max-md:px-2">
            <Checkbox
                checked={selection.isChosen(tenant.id)}
                onCheckedChange={(checked) =>
                    selection.toggle(tenant.id, checked === true)
                }
                aria-label={`Elegir ${tenant.trade_name}`}
                className="touch-hit"
            />
        </TableCell>
    );
}
