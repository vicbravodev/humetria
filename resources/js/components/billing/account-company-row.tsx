import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { countOf } from '@/lib/plural';
import type { AccountCompanyRow } from '@/types';

/** A company the account pays: who it is, where it stands, its centers and the plan it contracted. */
export default function AccountCompanyRowView({
    company,
}: {
    company: AccountCompanyRow;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-60 whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={company.trade_name} kind="company" />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">
                            {company.trade_name}
                        </span>
                        <span className="text-caption text-muted-foreground tabular-nums">
                            {company.rfc}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={tenantStatusVariant(company.status)}>
                    {company.status_label}
                </Badge>
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {countOf(company.work_centers, 'centro', 'centros')}
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                Sin plan asignado
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                Desde {company.linked_since}
            </TableCell>
        </TableRow>
    );
}
