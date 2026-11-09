import CenterCard from '@/components/attendance/center-card';
import Pagination from '@/components/humetria/pagination';
import type { OverviewCenter, Paginated } from '@/types';

/** The centers in use, a page at a time, each with whether its people can mark in it and what it lacks. */
export default function CentersZones({
    centers,
    week,
    requiresQr,
}: {
    centers: Paginated<OverviewCenter>;
    week: string;
    requiresQr: boolean;
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
            <div className="grid gap-4 min-[1100px]:grid-cols-2">
                {centers.data.map((center) => (
                    <CenterCard
                        key={center.id}
                        center={center}
                        week={week}
                        requiresQr={requiresQr}
                    />
                ))}
            </div>
            <Pagination
                page={centers}
                label="Páginas de centros"
                pinned={false}
            />
        </section>
    );
}
