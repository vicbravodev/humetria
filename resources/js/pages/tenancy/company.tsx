import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import CompanyContactCard from '@/components/tenancy/company-contact-card';
import CompanyHeader from '@/components/tenancy/company-header';
import CompanyMain from '@/components/tenancy/company-main';
import CompanyNotes from '@/components/tenancy/company-notes';
import CompanyOverdueNote from '@/components/tenancy/company-overdue-note';
import CompanyRiskZone from '@/components/tenancy/company-risk-zone';
import CompanyTabs from '@/components/tenancy/company-tabs';
import { companyTab } from '@/lib/company-360';
import { index } from '@/routes/companies';
import type { CompanyPage } from '@/types';

/**
 * One company in the console (tablero Empresa 360): its head card, its tabs, the notice of what it owes, the open tab, and at
 * the side who to talk to, the staff's notes and the risk zone.
 */
export default function Company(props: CompanyPage) {
    const { company, activity } = props;
    const { auth } = usePage().props;
    const url = usePage().url;
    const asked = companyTab(url);
    const tab = asked === 'actividad' && activity === null ? 'resumen' : asked;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index() },
            { title: company.trade_name },
        ],
    });

    return (
        <>
            <Head title={company.trade_name} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <CompanyHeader page={props} />
                <CompanyTabs
                    companyId={company.id}
                    active={tab}
                    counts={{
                        miembros: props.members.total,
                        invitaciones: props.invitations.total,
                    }}
                    hidden={activity === null ? ['actividad'] : []}
                />
                <CompanyOverdueNote payer={props.payer} />
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <CompanyMain page={props} tab={tab} />
                    <aside className="grid gap-6">
                        <CompanyContactCard
                            company={company}
                            plan={props.plan}
                            payer={props.payer}
                        />
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
