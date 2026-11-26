import { Link } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import CompanyMoreMenu from '@/components/tenancy/company-more-menu';
import TenantSupportActions from '@/components/tenancy/tenant-support-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { companyHeaderFacts, overdueBadge } from '@/lib/company-360';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { show as account } from '@/routes/billing/accounts';
import type { CompanyPage } from '@/types';

/**
 * The head of a company's page (tablero Empresa 360, on PageHeader): its name and where it stands, who it is legally and who answers
 * for it, and what the staff does most: enter as support, change its plan, record a payment and the rest under «Más».
 */
export default function CompanyHeader({ page }: { page: CompanyPage }) {
    const { company, plan, payer } = page;
    const overdue = overdueBadge(payer);

    return (
        <PageHeader
            caption="Empresa"
            title={company.trade_name}
            status={
                <>
                    <Badge variant={tenantStatusVariant(company.status)}>
                        {company.status_label}
                    </Badge>
                    {overdue !== null && (
                        <Badge variant="destructive">{overdue}</Badge>
                    )}
                    {plan !== null && <Badge>{plan.name}</Badge>}
                    {company.type === 'union' && (
                        <Badge variant="outline">Sindicato</Badge>
                    )}
                </>
            }
            facts={companyHeaderFacts(company)}
        >
            <TenantSupportActions
                tenantId={company.id}
                tradeName={company.trade_name}
                hasOwner={company.owner !== null}
                variant="outline"
            />
            {payer !== null && (
                <>
                    <Button asChild variant="outline">
                        <Link href={account.url(payer.account_id)}>
                            Cambiar plan
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={account.url(payer.account_id)}>
                            Registrar pago
                        </Link>
                    </Button>
                </>
            )}
            <CompanyMoreMenu
                companyId={company.id}
                accountId={payer?.account_id ?? null}
                canAudit={page.can_audit}
            />
        </PageHeader>
    );
}
