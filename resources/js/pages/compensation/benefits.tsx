import { Head } from '@inertiajs/react';
import BenefitsEmpty from '@/components/compensation/benefits-empty';
import BenefitsTable from '@/components/compensation/benefits-table';
import BenefitsToolbar from '@/components/compensation/benefits-toolbar';
import CreateBenefitSheet from '@/components/compensation/create-benefit-sheet';
import RiskPremiumCard from '@/components/compensation/risk-premium-card';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { benefitQuery, benefitsHeadline } from '@/lib/compensation/benefits';
import { grades } from '@/routes/tenant/compensation/benefits';
import type {
    BenefitCounts,
    BenefitFilters,
    BenefitRow,
    ChoiceOption,
    Paginated,
    RiskPremium,
} from '@/types';

export default function Benefits({
    benefits,
    counts,
    filters,
    kinds,
    frequencies,
    groups,
    sbc_integrations,
    risk_premium,
}: {
    benefits: Paginated<BenefitRow>;
    counts: BenefitCounts;
    filters: BenefitFilters;
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    groups: ChoiceOption[];
    sbc_integrations: ChoiceOption[];
    risk_premium: RiskPremium | null;
}) {
    const tenantSlug = useTenantSlug();
    const choices = {
        kinds,
        frequencies,
        groups,
        sbcIntegrations: sbc_integrations,
        gradesUrl: grades.url(tenantSlug),
    };
    const filtered =
        Object.keys(benefitQuery({ ...filters, status: 'active' })).length > 0;

    return (
        <>
            <Head title="Prestaciones" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Prestaciones"
                    description={`${benefitsHeadline(counts)} Aquí defines lo que da el contrato y sus términos de hoy: el punto de partida de cada escenario.`}
                >
                    <CreateBenefitSheet choices={choices} />
                </PageHeader>
                <RiskPremiumCard premium={risk_premium} />
                <BenefitsToolbar filters={filters} counts={counts} />
                {benefits.data.length === 0 ? (
                    <BenefitsEmpty
                        filtered={filtered}
                        archived={filters.status === 'archived'}
                    />
                ) : (
                    <BenefitsTable benefits={benefits.data} choices={choices} />
                )}
                <Pagination page={benefits} label="Páginas de prestaciones" />
            </div>
        </>
    );
}

Benefits.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Prestaciones' },
    ],
};
