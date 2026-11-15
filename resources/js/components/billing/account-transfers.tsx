import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
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
import type { Paginated, TransferRow } from '@/types';

/** The companies that left the account or came to it, the latest cut first, with the reason and who decided it. */
export default function AccountTransfers({
    transfers,
}: {
    transfers: Paginated<TransferRow>;
}) {
    return (
        <section aria-labelledby="account-transfers" className="grid gap-4">
            <h2
                id="account-transfers"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Traslados
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Empresas que salieron de la cuenta o llegaron a ella, del
                    corte más reciente al más antiguo
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Empresa</TableHead>
                        <TableHead>Movimiento</TableHead>
                        <TableHead>Desde el corte</TableHead>
                        <TableHead>Motivo</TableHead>
                        <TableHead>Lo decidió</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {transfers.data.map((transfer) => (
                        <TableRow key={transfer.id}>
                            <TableCell className="min-w-48 font-medium whitespace-normal">
                                {transfer.company}
                            </TableCell>
                            <TableCell className="min-w-56 text-sm whitespace-normal">
                                <span className="flex items-center gap-2">
                                    {transfer.direction === 'out' ? (
                                        <ArrowUpRight
                                            aria-hidden
                                            className="size-4"
                                        />
                                    ) : (
                                        <ArrowDownLeft
                                            aria-hidden
                                            className="size-4"
                                        />
                                    )}
                                    {transfer.direction === 'out'
                                        ? `Salió hacia ${transfer.other}`
                                        : `Llegó desde ${transfer.other}`}
                                </span>
                                {transfer.profile !== null && (
                                    <span className="block text-caption text-muted-foreground">
                                        Cobro: {transfer.profile}
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="text-sm tabular-nums">
                                {transfer.effective_on}
                            </TableCell>
                            <TableCell className="min-w-56 text-sm whitespace-normal">
                                {transfer.reason}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {transfer.by}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguos"
                page={transfers}
                label="Páginas de traslados"
            />
        </section>
    );
}
