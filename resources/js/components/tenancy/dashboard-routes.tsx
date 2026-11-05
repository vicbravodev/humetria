import Pagination from '@/components/humetria/pagination';
import DashboardRouteNorm from '@/components/tenancy/dashboard-route-norm';
import { normsInUse } from '@/lib/dashboard';
import type { CenterRoutes, Paginated } from '@/types';

/** "Dónde vas" (board V2-Panel): each standard that applies, with every center of the page and its six stages. */
export default function DashboardRoutes({
    centers,
}: {
    centers: Paginated<CenterRoutes>;
}) {
    return (
        <section aria-labelledby="routes" className="flex flex-col gap-3">
            <h2 id="routes" className="text-xl leading-7 font-semibold">
                Dónde vas
            </h2>
            <div className="@container overflow-hidden card">
                {normsInUse(centers.data).map(({ norm, rows }) => (
                    <DashboardRouteNorm
                        key={norm.key}
                        norm={norm}
                        rows={rows}
                    />
                ))}
                {centers.last_page > 1 && (
                    <div className="border-t px-6 py-3">
                        <Pagination
                            page={centers}
                            label="Páginas de centros de trabajo"
                            pinned={false}
                        />
                    </div>
                )}
            </div>
        </section>
    );
}
