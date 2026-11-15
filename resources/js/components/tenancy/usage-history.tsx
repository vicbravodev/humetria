import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatTokens } from '@/lib/usage';
import type { Paginated, UsageMonth } from '@/types';

/** The months a company has used Humi in, the latest first, with the quota each one had. */
export default function UsageHistory({
    months,
}: {
    months: Paginated<UsageMonth>;
}) {
    if (months.total === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Esta empresa aún no usa Humi.
            </p>
        );
    }

    return (
        <div className="grid gap-3">
            <Table>
                <TableCaption className="sr-only">
                    Uso de Humi por mes
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Mes</TableHead>
                        <TableHead className="text-right">Usados</TableHead>
                        <TableHead className="text-right">Cuota</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {months.data.map((month) => (
                        <TableRow key={month.period}>
                            <TableCell className="font-medium">
                                {month.label}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {formatTokens(month.used)}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                {month.quota === null
                                    ? '—'
                                    : formatTokens(month.quota)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={months}
                label="Páginas del uso de Humi"
                pinned={false}
            />
        </div>
    );
}
