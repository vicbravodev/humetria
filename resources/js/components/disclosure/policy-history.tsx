import { Link } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
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
import { version } from '@/routes/tenant/disclosure/policy';
import type { Paginated, PolicyVersionRow } from '@/types';

/** Every version published, the newest first, a page at a time (num. 10.4: nothing is deleted). */
export default function PolicyHistory({
    history,
}: {
    history: Paginated<PolicyVersionRow>;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            id="policy-history"
            aria-label="Historial de versiones"
            className="flex flex-col gap-3.5"
        >
            <h2 className="text-lg leading-[26px] font-semibold">
                Historial de versiones
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Versiones publicadas de la política
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Versión</TableHead>
                        <TableHead>Publicada el</TableHead>
                        <TableHead>Incisos</TableHead>
                        <TableHead>Centros</TableHead>
                        <TableHead>
                            <span className="sr-only">Leer</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {history.data.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="font-medium">
                                {row.version}
                            </TableCell>
                            <TableCell>{row.publishedOn}</TableCell>
                            <TableCell>
                                <Badge
                                    variant={
                                        row.covers.length === 3
                                            ? 'success'
                                            : 'warning'
                                    }
                                >
                                    {row.covers.length === 0
                                        ? 'Ninguno'
                                        : row.covers.join(', ')}
                                </Badge>
                            </TableCell>
                            <TableCell>{row.centers}</TableCell>
                            <TableCell className="text-right">
                                <Link
                                    href={version({
                                        tenant: tenantSlug,
                                        document: row.id,
                                    })}
                                    className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Leer
                                    <span className="sr-only">
                                        {' '}
                                        la versión {row.version}
                                    </span>
                                </Link>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={history}
                label="Páginas del historial"
                previous="Ver más recientes"
                next="Ver más antiguas"
            />
        </section>
    );
}
