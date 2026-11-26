import type { ReactNode } from 'react';
import CompanyActivity from '@/components/tenancy/company-activity';
import CompanyBilling from '@/components/tenancy/company-billing';
import CompanyInvitations from '@/components/tenancy/company-invitations';
import CompanyLimits from '@/components/tenancy/company-limits';
import CompanyMembers from '@/components/tenancy/company-members';
import CompanyModules from '@/components/tenancy/company-modules';
import CompanyOtherUsage from '@/components/tenancy/company-other-usage';
import CompanyOverview from '@/components/tenancy/company-overview';
import CompanySection from '@/components/tenancy/company-section';
import CompanyUsage from '@/components/tenancy/company-usage';
import type { CompanyTab } from '@/lib/company-360';
import type { CompanyPage } from '@/types';

const TABS: Record<CompanyTab, (page: CompanyPage) => ReactNode> = {
    resumen: (page) => <CompanyOverview page={page} />,
    modulos: (page) => (
        <CompanyModules
            companyId={page.company.id}
            modules={page.modules}
            canManage={page.can_manage}
        />
    ),
    uso: (page) => (
        <>
            <CompanyLimits limits={page.limits} />
            <CompanyOtherUsage rows={page.other_usage} />
        </>
    ),
    facturacion: (page) => (
        <CompanySection id="facturacion" title="Facturación">
            <CompanyBilling plan={page.plan} />
        </CompanySection>
    ),
    miembros: (page) => (
        <CompanySection id="miembros" title="Miembros">
            <CompanyMembers members={page.members} />
        </CompanySection>
    ),
    invitaciones: (page) => (
        <CompanySection id="invitaciones" title="Invitaciones">
            <CompanyInvitations invitations={page.invitations} />
        </CompanySection>
    ),
    humi: (page) => (
        <CompanyUsage
            companyId={page.company.id}
            usage={page.usage}
            history={page.usage_history}
            canManage={page.can_manage}
        />
    ),
    actividad: (page) =>
        page.activity === null ? null : (
            <CompanyActivity
                companyId={page.company.id}
                activity={page.activity}
            />
        ),
};

/** What the open tab of a company's page holds. */
export default function CompanyMain({
    page,
    tab,
}: {
    page: CompanyPage;
    tab: CompanyTab;
}) {
    return <div className="grid min-w-0 gap-6">{TABS[tab](page)}</div>;
}
