import { ListChecks } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import RiskLevel from '@/components/humetria/risk-level';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { SCOPE_LABELS } from '@/lib/interventions';
import type { ProgramRow } from '@/types';

/** The rows of the Programa: what it attends and the highest level that led to each (criterio C-30). */
export default function ProgramRows({ rows }: { rows: ProgramRow[] }) {
    if (rows.length === 0) {
        return (
            <EmptyState icon={ListChecks} title="El Programa no tiene filas">
                Agrega las categorías o dominios que quieras atender.
            </EmptyState>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Qué se atiende</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Nivel más alto</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium">
                            {row.label}
                        </TableCell>
                        <TableCell>{SCOPE_LABELS[row.scope]}</TableCell>
                        <TableCell>
                            {row.level === null ? (
                                'Agregada por RH'
                            ) : (
                                <RiskLevel
                                    level={row.level.value}
                                    label={row.level.label}
                                />
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
