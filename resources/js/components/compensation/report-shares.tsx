import RevokeShareDialog from '@/components/compensation/revoke-share-dialog';
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
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Paginated, ReportShareRow } from '@/types';

/** The links to the report that still open, each one revocable once confirmed. Nothing when there are none. */
export default function ReportShares({
    scenarioId,
    shares,
}: {
    scenarioId: string;
    shares: Paginated<ReportShareRow>;
}) {
    const tenantSlug = useTenantSlug();

    if (shares.total === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4 print:hidden">
            <div className="flex flex-col gap-1">
                <h2 className="text-lg font-semibold">Enlaces abiertos</h2>
                <p className="text-sm text-muted-foreground">
                    Quien tenga uno abre el reporte sin cuenta hasta que vence o
                    lo revocas.
                </p>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Enlaces temporales al reporte que siguen abiertos
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Creado</TableHead>
                        <TableHead>Vence</TableHead>
                        <TableHead className="text-right">Acción</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {shares.data.map((share) => (
                        <TableRow key={share.id}>
                            <TableCell>{share.createdAt}</TableCell>
                            <TableCell>{share.expiresAt}</TableCell>
                            <TableCell className="text-right">
                                <RevokeShareDialog
                                    tenant={tenantSlug}
                                    scenarioId={scenarioId}
                                    share={share}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={shares}
                label="Páginas de enlaces"
                pinned={false}
            />
        </section>
    );
}
