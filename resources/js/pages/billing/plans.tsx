import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import NegotiatedPrices from '@/components/billing/negotiated-prices';
import PlanCard from '@/components/billing/plan-card';
import PlanModulesTable from '@/components/billing/plan-modules-table';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { mostUsed } from '@/lib/billing-catalog';
import type { CatalogView, NegotiatedPriceRow, Paginated } from '@/types';

/**
 * The catalog in force (tablero Planes y precios), read only: each plan with its price and modules, then which module each
 * brings and, at the side, the prices agreed by hand. Changing it is a change of code with a new version, which never touches
 * who already signed.
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
    const popular = mostUsed(catalog.plans);

    return (
        <>
            <Head title="Planes y precios" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Planes y precios"
                    status={<Badge variant="secondary">Solo lectura</Badge>}
                    description="El catálogo que usan las suscripciones: qué incluye cada plan, cuánto cuesta y quién tiene un precio especial. Un cambio de catálogo no toca a quien ya contrató; solo a nuevas suscripciones."
                />
                <section
                    aria-label="Planes del catálogo"
                    className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,260px),1fr))] gap-6"
                >
                    {catalog.plans.map((plan) => (
                        <PlanCard
                            key={plan.key}
                            plan={plan}
                            popular={plan.key === popular}
                        />
                    ))}
                </section>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <PlanModulesTable catalog={catalog} />
                    <aside
                        aria-label="Precios especiales"
                        className="flex min-w-0 flex-col gap-6"
                    >
                        <NegotiatedPrices
                            negotiated={negotiated}
                            filters={filters}
                        />
                        <p className="flex gap-3 rounded-xl bg-accent px-4 py-3.5 text-meta text-accent-foreground">
                            <Info
                                aria-hidden
                                className="mt-0.5 size-[18px] shrink-0"
                            />
                            <span>
                                Catálogo {catalog.version}, vigente desde el{' '}
                                {catalog.effective_on}. Precios sin IVA (
                                {catalog.vat_percent} %). Un catálogo nuevo es
                                una versión nueva en el código.
                            </span>
                        </p>
                    </aside>
                </div>
            </div>
        </>
    );
}
