import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import CompanyUsage from '@/components/tenancy/company-usage';
import CompanyLimits from '@/components/tenancy/company-limits';
import CompanyModules from '@/components/tenancy/company-modules';
import CompanyOtherUsage from '@/components/tenancy/company-other-usage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { index as audit } from '@/routes/audit';
import { index } from '@/routes/companies';
import type {
    CompanyDetail,
    LimitRow,
    ModuleRow,
    OtherUsage,
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
    other_usage: otherUsage,
    usage_history: usageHistory,
    can_manage: canManage,
    can_audit: canAudit,
}: {
    company: CompanyDetail;
    modules: ModuleRow[];
    limits: LimitRow[];
    usage: UsageSummary;
    other_usage: OtherUsage[];
    usage_history: Paginated<UsageMonth>;
    can_manage: boolean;
    can_audit: boolean;
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
                        <>
                            <Badge
                                variant={tenantStatusVariant(company.status)}
                            >
                                {company.status_label}
                            </Badge>
                            {company.type === 'union' && (
                                <Badge variant="outline">Sindicato</Badge>
                            )}
                        </>
                    }
                    description={`${company.legal_name} · ${company.rfc}`}
                >
                    {canAudit && (
                        <Button asChild variant="outline">
                            <Link
                                href={audit.url({
                                    query: { company: company.id },
                                })}
                            >
                                <ScrollText aria-hidden />
                                Ver su bitácora
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                <CompanyLimits limits={limits} />
                <CompanyUsage
                    companyId={company.id}
                    usage={usage}
                    history={usageHistory}
                    canManage={canManage}
                />
                <CompanyOtherUsage rows={otherUsage} />
                <CompanyModules
                    companyId={company.id}
                    modules={modules}
                    canManage={canManage}
                />
            </div>
        </>
    );
}
