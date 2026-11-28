import StackedTable from '@/components/humetria/stacked-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { count, since } from '@/lib/operations';
import type { FailedJobKind } from '@/types';

/**
 * The jobs that failed for good in the last 7 days, by kind (tablero Salud): never their payload or error, which can carry a
 * person's data, so neither the company. Retrying them is Horizon's.
 */
export default function FailedJobsCard({ week }: { week: FailedJobKind[] }) {
    const total = week.reduce((sum, kind) => sum + kind.count, 0);

    return (
        <section className="overflow-hidden card [&_[data-slot=table-container]]:rounded-none [&_[data-slot=table-container]]:border-0">
            <header className="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                <h2 className="flex-1 text-base font-semibold">
                    Trabajos fallidos
                </h2>
                {total > 0 && (
                    <Badge variant="warning">{count(total)} en 7 días</Badge>
                )}
                <Button variant="outline" size="sm" asChild>
                    <a href="/horizon/failed">Reintentar en Horizon</a>
                </Button>
            </header>
            {week.length === 0 ? (
                <p className="px-6 py-4 text-sm text-muted-foreground">
                    Ningún trabajo falló en los últimos 7 días.
                </p>
            ) : (
                <StackedTable>
                    <TableHeader>
                        <TableRow className="bg-muted hover:bg-muted">
                            <TableHead>Trabajo</TableHead>
                            <TableHead>Módulo</TableHead>
                            <TableHead>Cola</TableHead>
                            <TableHead className="text-right">Veces</TableHead>
                            <TableHead>Falló</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {week.map((kind) => (
                            <TableRow key={`${kind.job}-${kind.queue}`}>
                                <TableCell className="font-medium max-md:w-full">
                                    {kind.job}
                                </TableCell>
                                <TableCell>{kind.module ?? '—'}</TableCell>
                                <TableCell>{kind.queue}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {count(kind.count)}
                                    {/* On a phone the head is gone: the cell says what it counts. */}
                                    <span className="md:sr-only"> veces</span>
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {since(kind.lastFailedAt)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </StackedTable>
            )}
        </section>
    );
}
