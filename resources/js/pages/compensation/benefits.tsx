import { Head, setLayoutProps } from '@inertiajs/react';
import BenefitsEmpty from '@/components/compensation/benefits-empty';
import BenefitsTable from '@/components/compensation/benefits-table';
import BenefitsToolbar from '@/components/compensation/benefits-toolbar';
import ContractDataFrame from '@/components/compensation/contract-data-frame';
import NewBenefitButton from '@/components/compensation/new-benefit-button';
import RiskPremiumCard from '@/components/compensation/risk-premium-card';
import Pagination from '@/components/humetria/pagination';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { benefitQuery, benefitsHeadline } from '@/lib/compensation/benefits';
import type { ContractStatus } from '@/lib/compensation/start';
import { index } from '@/routes/tenant/compensation';
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
    status,
}: {
    benefits: Paginated<BenefitRow>;
    counts: BenefitCounts;
    filters: BenefitFilters;
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    groups: ChoiceOption[];
    sbc_integrations: ChoiceOption[];
    risk_premium: RiskPremium | null;
    status: ContractStatus;
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
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: 'Datos del contrato' },
        ],
    });

    return (
        <>
            <Head title="Prestaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ContractDataFrame
                    status={status}
                    current="benefits"
                    title="Prestaciones"
                    lead={`${benefitsHeadline(counts)} Aquí defines lo que da el contrato y sus términos de hoy: el punto de partida de cada escenario.`}
                    action={<NewBenefitButton />}
                >
                    <RiskPremiumCard premium={risk_premium} />
                    <BenefitsToolbar filters={filters} counts={counts} />
                    {benefits.data.length === 0 ? (
                        <BenefitsEmpty
                            filtered={filtered}
                            archived={filters.status === 'archived'}
                        />
                    ) : (
                        <BenefitsTable
                            benefits={benefits.data}
                            choices={choices}
                        />
                    )}
                    <Pagination
                        page={benefits}
                        label="Páginas de prestaciones"
                    />
                </ContractDataFrame>
            </div>
        </>
    );
}
