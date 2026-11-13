import { shortDate } from '@/lib/calendar-date';
import { Wrench } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import ReportResolveForm from '@/components/seating/report-resolve-form';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, SeatReportRow } from '@/types';

/** The reports of damaged seats of a center: what was said, where, when, and how it was resolved (or the form to say so). */
export default function ReportsTable({
    reports,
    open,
    canResolve,
}: {
    reports: Paginated<SeatReportRow>;
    open: boolean;
    canResolve: boolean;
}) {
    if (reports.total === 0) {
        return (
            <EmptyState
                icon={Wrench}
                title={
                    open ? 'Sin reportes abiertos' : 'Sin reportes resueltos'
                }
            >
                {open
                    ? 'Cuando alguien avise de un asiento dañado desde su página, aparecerá aquí.'
                    : 'Los reportes que se resuelvan quedan aquí como evidencia.'}
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Reporte</TableHead>
                        <TableHead>Puesto</TableHead>
                        <TableHead>Fecha</TableHead>
                        <TableHead>
                            {open ? 'Resolver' : 'Resolución'}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {reports.data.map((report) => (
                        <TableRow key={report.id}>
                            <TableCell className="max-w-sm whitespace-normal">
                                {report.description}
                            </TableCell>
                            <TableCell>{report.positionName ?? '—'}</TableCell>
                            <TableCell>
                                {shortDate(report.reportedOn)}
                            </TableCell>
                            <TableCell className="min-w-64 whitespace-normal">
                                {open ? (
                                    canResolve && (
                                        <ReportResolveForm
                                            reportId={report.id}
                                        />
                                    )
                                ) : (
                                    <>
                                        {report.resolution}
                                        <span className="block text-caption text-muted-foreground">
                                            {report.resolvedOn === null
                                                ? ''
                                                : shortDate(report.resolvedOn)}
                                        </span>
                                    </>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={reports}
                label="Páginas de reportes"
                pinned={false}
            />
        </>
    );
}
