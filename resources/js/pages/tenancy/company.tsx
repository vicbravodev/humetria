import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import CompanyUsage from '@/components/tenancy/company-usage';
import CompanyLimits from '@/components/tenancy/company-limits';
import CompanyModules from '@/components/tenancy/company-modules';
import { Badge } from '@/components/ui/badge';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { index } from '@/routes/companies';
import type {
    CompanyDetail,
    LimitRow,
    ModuleRow,
    Paginated,
    UsageMonth,
    UsageSummary,
} from '@/types';

/** One company in the console: who it is, where it stands and which modules it has, with where each comes from, and what it has used of its plan's limits. */
export default function Company({
    company,
    modules,
    limits,
    usage,
    usage_history: usageHistory,
    can_manage: canManage,
}: {
    company: CompanyDetail;
    modules: ModuleRow[];
    limits: LimitRow[];
    usage: UsageSummary;
    usage_history: Paginated<UsageMonth>;
    can_manage: boolean;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index() },
            { title: company.trade_name },
        ],
    });

    return (
        <>
            <Head title={company.trade_name} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Empresa"
                    title={company.trade_name}
                    status={
                        <Badge variant={tenantStatusVariant(company.status)}>
                            {company.status_label}
                        </Badge>
                    }
                    description={`${company.legal_name} · ${company.rfc}`}
                />
                <CompanyLimits limits={limits} />
                <CompanyUsage
                    companyId={company.id}
                    usage={usage}
                    history={usageHistory}
                    canManage={canManage}
                />
                <CompanyModules
                    companyId={company.id}
                    modules={modules}
                    canManage={canManage}
                />
            </div>
        </>
    );
}
