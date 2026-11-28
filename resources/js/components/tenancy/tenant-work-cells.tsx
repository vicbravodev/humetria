import ProgressBar from '@/components/humetria/progress-bar';
import { TableCell } from '@/components/ui/table';
import { monthlyText, seatsShare, seatsText } from '@/lib/tenant-work';
import type { TenantRow } from '@/types';

const muted = (missing: boolean): string | undefined =>
    missing ? 'text-muted-foreground' : undefined;

/** What the staff works a company by (tablero Empresas): its plan, the seats it uses, what it pays a month and when it renews. */
export default function TenantWorkCells({ tenant }: { tenant: TenantRow }) {
    const { seats, plan } = tenant;

    return (
        <>
            <TableCell className="hidden text-sm lg:table-cell">
                <span className={muted(plan === null)}>
                    {plan?.name ?? 'Sin plan'}
                </span>
            </TableCell>
            <TableCell className="hidden text-sm tabular-nums lg:table-cell">
                {seats === null ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <div
                        className="flex items-center gap-2"
                        title={seatsText(seats)}
                    >
                        <div className="w-12">
                            <ProgressBar
                                value={seats.used}
                                max={seats.amount}
                                label={`Asientos de ${tenant.trade_name} en uso: ${seatsText(seats)}`}
                            />
                        </div>
                        <span>{seatsShare(seats)}</span>
                    </div>
                )}
            </TableCell>
            <TableCell className="hidden text-right text-sm tabular-nums xl:table-cell">
                <span className={muted(plan?.monthly_cents == null)}>
                    {monthlyText(plan)}
                </span>
            </TableCell>
            <TableCell className="hidden text-sm tabular-nums min-[1400px]:table-cell min-[1600px]:hidden">
                <span className={muted(plan === null)}>
                    {plan?.renews_on ?? '—'}
                </span>
            </TableCell>
        </>
    );
}
