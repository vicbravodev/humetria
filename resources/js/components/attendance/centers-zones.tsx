import CenterZonesRow from '@/components/attendance/center-zones-row';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { OverviewCenter, Paginated } from '@/types';

/** The centers in use, a page at a time: where people can mark and with what. */
export default function CentersZones({
    centers,
    week,
}: {
    centers: Paginated<OverviewCenter>;
    week: string;
}) {
    return (
        <section
            id="centros"
            aria-labelledby="centros-title"
            className="flex scroll-mt-20 flex-col gap-4"
        >
            <div>
                <h2
                    id="centros-title"
                    className="text-xl leading-7 font-semibold"
                >
                    Centros y zonas
                </h2>
                <p className="text-sm text-muted-foreground">
                    Las personas marcan dentro de una zona de su centro o con el
                    código de su pantalla.
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Centro</TableHead>
                        <TableHead>Personas</TableHead>
                        <TableHead>Zonas</TableHead>
                        <TableHead>Pantallas</TableHead>
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {centers.data.map((center) => (
                        <CenterZonesRow
                            key={center.id}
                            center={center}
                            week={week}
                        />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={centers}
                label="Páginas de centros"
                pinned={false}
            />
        </section>
    );
}
