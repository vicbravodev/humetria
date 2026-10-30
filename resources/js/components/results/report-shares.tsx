import { router } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy } from '@/routes/tenant/campaigns/shares';
import type { Paginated, ReportShareRow } from '@/types';

/** The links to the report that still open, each one revocable. Nothing when there are none. */
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
        <section className="grid gap-3 print:hidden">
            <h2 className="text-xl font-semibold">Enlaces abiertos</h2>
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
                                <Button
                                    variant="outline"
                                    size="sm"
                                    aria-label={`Revocar el enlace que vence el ${share.expiresAt}`}
                                    onClick={() =>
                                        router.visit(
                                            destroy([
                                                tenantSlug,
                                                campaignId,
                                                share.id,
                                            ]),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Revocar
                                </Button>
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
