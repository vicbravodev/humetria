import { Link } from '@inertiajs/react';
import CompanyMark from '@/components/tenancy/company-mark';
import CompanyMoreMenu from '@/components/tenancy/company-more-menu';
import TenantSupportActions from '@/components/tenancy/tenant-support-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { companyLine, overdueBadge } from '@/lib/company-360';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { show as account } from '@/routes/billing/accounts';
import type { CompanyPage } from '@/types';

/**
 * The head of a company's page (tablero Empresa 360): its logo, name and where it stands, who it is legally and who answers
 * for it, and what the staff does most: enter as support, change its plan, record a payment and the rest under «Más».
 */
export default function CompanyHeader({ page }: { page: CompanyPage }) {
    const { company, plan, payer } = page;
    const overdue = overdueBadge(payer);

    return (
        <header className="flex flex-wrap items-center gap-5 card px-6 py-5">
            <CompanyMark name={company.trade_name} logoUrl={company.logo_url} />
            <div className="flex min-w-0 flex-[1_1_320px] flex-col gap-1.5">
                <div className="flex flex-wrap items-center gap-2.5">
                    <h1 className="text-[30px] leading-[38px] font-semibold tracking-[-0.01em]">
                        {company.trade_name}
                    </h1>
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
                </div>
                <p className="[overflow-wrap:anywhere] text-muted-foreground">
                    {companyLine(company)}
                </p>
            </div>
            <div className="flex flex-wrap gap-2.5">
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
            </div>
        </header>
    );
}
