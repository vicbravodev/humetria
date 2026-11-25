import CompanyActivity from '@/components/tenancy/company-activity';
import CompanyBilling from '@/components/tenancy/company-billing';
import CompanyInvitations from '@/components/tenancy/company-invitations';
import CompanyLimits from '@/components/tenancy/company-limits';
import CompanyMembers from '@/components/tenancy/company-members';
import CompanyModules from '@/components/tenancy/company-modules';
import CompanyOtherUsage from '@/components/tenancy/company-other-usage';
import CompanySection from '@/components/tenancy/company-section';
import CompanyUsage from '@/components/tenancy/company-usage';
import type { CompanyPage } from '@/types';

/** The parts of a company's page, in the order of its tabs. */
export default function CompanyMain({ page }: { page: CompanyPage }) {
    const { company, can_manage: canManage, activity } = page;

    return (
        <div className="grid min-w-0 gap-10">
            <CompanySection shown={false} id="resumen" title="Resumen">
                <CompanyLimits limits={page.limits} />
            </CompanySection>
            <CompanySection shown={false} id="modulos" title="Módulos">
                <CompanyModules
                    companyId={company.id}
                    modules={page.modules}
                    canManage={canManage}
                />
            </CompanySection>
            <CompanySection shown={false} id="uso" title="Uso y límites">
                <CompanyUsage
                    companyId={company.id}
                    usage={page.usage}
                    history={page.usage_history}
                    canManage={canManage}
                />
                <CompanyOtherUsage rows={page.other_usage} />
            </CompanySection>
            <CompanySection id="facturacion" title="Facturación">
                <CompanyBilling plan={page.plan} />
            </CompanySection>
            <CompanySection id="miembros" title="Miembros">
                <CompanyMembers members={page.members} />
            </CompanySection>
            <CompanySection id="invitaciones" title="Invitaciones">
                <CompanyInvitations invitations={page.invitations} />
            </CompanySection>
            {activity !== null && (
                <CompanySection id="actividad" title="Actividad">
                    <CompanyActivity
                        companyId={company.id}
                        activity={activity}
                    />
                </CompanySection>
            )}
        </div>
    );
}
