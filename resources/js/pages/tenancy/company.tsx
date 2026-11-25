import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SectionTabs from '@/components/humetria/section-tabs';
import CompanyContactCard from '@/components/tenancy/company-contact-card';
import CompanyMain from '@/components/tenancy/company-main';
import CompanyNotes from '@/components/tenancy/company-notes';
import CompanyRiskZone from '@/components/tenancy/company-risk-zone';
import TenantSupportActions from '@/components/tenancy/tenant-support-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { index as audit } from '@/routes/audit';
import { index } from '@/routes/companies';
import type { CompanyPage } from '@/types';

const SECTIONS = [
    { id: 'resumen', label: 'Resumen' },
    { id: 'modulos', label: 'Módulos' },
    { id: 'uso', label: 'Uso y límites' },
    { id: 'facturacion', label: 'Facturación' },
    { id: 'miembros', label: 'Miembros' },
    { id: 'invitaciones', label: 'Invitaciones' },
    { id: 'actividad', label: 'Actividad' },
];

/**
 * One company in the console (tablero Empresa 360): where it stands, its modules, what it uses, its plan, its people and
 * invitations, and its activity, each a tab away; at the side who to talk to, the staff's notes and the risk zone.
 */
export default function Company(props: CompanyPage) {
    const { company, plan, activity } = props;
    const { auth } = usePage().props;
    const sections = SECTIONS.filter(
        (section) => section.id !== 'actividad' || activity !== null,
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index() },
            { title: company.trade_name },
        ],
    });

    return (
        <>
            <Head title={company.trade_name} />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
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
                            {plan !== null && (
                                <Badge variant="outline">{plan.name}</Badge>
                            )}
                            {company.type === 'union' && (
                                <Badge variant="outline">Sindicato</Badge>
                            )}
                        </>
                    }
                    description={`${company.legal_name} · ${company.rfc}`}
                >
                    <TenantSupportActions
                        tenantId={company.id}
                        tradeName={company.trade_name}
                        hasOwner={company.owner !== null}
                        variant="outline"
                    />
                    {props.can_audit && (
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
                <SectionTabs
                    label="Partes de la empresa"
                    sections={sections}
                    className="top-0"
                />
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <CompanyMain page={props} />
                    <aside className="grid gap-6 lg:sticky lg:top-16">
                        <CompanyContactCard company={company} plan={plan} />
                        <CompanyNotes
                            companyId={company.id}
                            notes={props.notes}
                        />
                        {auth.isSuperadmin && (
                            <CompanyRiskZone company={company} />
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}
