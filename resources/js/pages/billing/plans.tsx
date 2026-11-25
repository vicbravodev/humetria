import { Head } from '@inertiajs/react';
import NegotiatedPrices from '@/components/billing/negotiated-prices';
import PlanCard from '@/components/billing/plan-card';
import PlanModulesTable from '@/components/billing/plan-modules-table';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import type { CatalogView, NegotiatedPriceRow, Paginated } from '@/types';

/**
 * The catalog in force (tablero Planes y precios), read only: each plan with its price and modules, which module each brings,
 * and the prices agreed by hand. Changing it is a change of code with a new version, which never touches who already signed.
 */
export default function Plans({
    catalog,
    negotiated,
    filters,
}: {
    catalog: CatalogView;
    negotiated: Paginated<NegotiatedPriceRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Planes y precios" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Planes y precios"
                    status={<Badge variant="secondary">Solo lectura</Badge>}
                    description={`Catálogo ${catalog.version}, vigente desde el ${catalog.effective_on}. Precios sin IVA (${catalog.vat_percent} %). Un catálogo nuevo es una versión nueva en el código: no cambia lo que ya se contrató.`}
                />
                <section
                    aria-label="Planes del catálogo"
                    className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5"
                >
                    {catalog.plans.map((plan) => (
                        <PlanCard key={plan.key} plan={plan} />
                    ))}
                </section>
                <PlanModulesTable catalog={catalog} />
                <NegotiatedPrices negotiated={negotiated} filters={filters} />
            </div>
        </>
    );
}
