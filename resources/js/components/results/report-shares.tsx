import ConsolePagination from '@/components/console-pagination';
import RevokeShareDialog from '@/components/results/revoke-share-dialog';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, ReportShareRow } from '@/types';

/** The links to the report that still open, each one revocable once confirmed. Nothing when there are none. */
export default function ReportShares({
    tenantSlug,
    campaignId,
    shares,
}: {
    tenantSlug: string;
    campaignId: string;
    shares: Paginated<ReportShareRow>;
}) {
    if (shares.total === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4 print:hidden">
            <div className="flex flex-col gap-1">
                <h2 className="text-lg font-semibold">Enlaces abiertos</h2>
                <p className="text-sm text-muted-foreground">
                    Quien tenga uno abre el informe sin cuenta hasta que vence o
                    lo revocas.
                </p>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Enlaces temporales al informe que siguen abiertos
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
                                    route={[tenantSlug, campaignId]}
                                    share={share}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <ConsolePagination
                page={shares}
                label="Páginas de enlaces"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
