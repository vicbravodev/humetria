import StackedTable from '@/components/humetria/stacked-table';
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
 * The jobs that failed for good in the last 7 days, by kind: never their payload or error, which can carry a person's data.
 * Retrying them is Horizon's.
 */
export default function FailedJobsCard({ week }: { week: FailedJobKind[] }) {
    return (
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">Trabajos fallidos</h2>
                <p className="text-sm text-muted-foreground">
                    Últimos 7 días, por tipo de trabajo. Reintentarlos se hace
                    en Horizon.
                </p>
            </header>
            {week.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    Ningún trabajo falló en los últimos 7 días.
                </p>
            ) : (
                <StackedTable>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Trabajo</TableHead>
                            <TableHead>Módulo</TableHead>
                            <TableHead>Cola</TableHead>
                            <TableHead className="text-right">Veces</TableHead>
                            <TableHead>Último</TableHead>
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
