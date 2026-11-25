import ProgressBar from '@/components/humetria/progress-bar';
import { TableCell } from '@/components/ui/table';
import { seatsText } from '@/lib/tenant-work';
import type { TenantRow } from '@/types';

/** What the staff works a company by: the seats it uses of its plan and who at Humetría answers for it. */
export default function TenantWorkCells({ tenant }: { tenant: TenantRow }) {
    const { seats, account_manager: manager } = tenant;

    return (
        <>
            <TableCell className="hidden text-sm tabular-nums lg:table-cell">
                <div className="grid w-20 gap-1.5">
                    <span
                        className={
                            seats === null ? 'text-muted-foreground' : undefined
                        }
                    >
                        {seatsText(seats)}
                    </span>
                    {seats !== null && (
                        <ProgressBar
                            value={seats.used}
                            max={seats.amount}
                            label={`Asientos de ${tenant.trade_name} en uso`}
                        />
                    )}
                </div>
            </TableCell>
            <TableCell className="hidden text-sm whitespace-normal 2xl:table-cell">
                {manager?.name ?? (
                    <span className="text-muted-foreground">Sin asignar</span>
                )}
            </TableCell>
        </>
    );
}
