import { Head, usePage } from '@inertiajs/react';
import { CalendarCheck, ListChecks, Users } from 'lucide-react';
import PageHeader from '@/components/page-header';
import type { PageFact } from '@/components/page-header';
import CommitteePanel from '@/components/telework/committee-panel';
import { describeDate } from '@/lib/calendar-date';
import type { Committee, CommitteePerson } from '@/types';

function committeeFacts(committee: Committee | null): PageFact[] {
    if (committee === null) {
        return [];
    }

    const written = describeDate(committee.constitutedOn);
    const people = committee.members.length;

    return [
        ...(written === undefined
            ? []
            : [{ icon: CalendarCheck, text: `Acta del ${written}` }]),
        {
            icon: Users,
            text: people === 1 ? '1 integrante' : `${people} integrantes`,
        },
        { icon: ListChecks, text: 'Borrador de la lista listo' },
    ];
}

export default function CommitteePage({
    workCenter,
    committee,
    candidates = [],
    canManage,
}: {
    workCenter: { id: string; name: string };
    committee: Committee | null;
    /** Only for whoever manages the committee. */
    candidates?: CommitteePerson[];
    canManage: boolean;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={`Comisión de ${workCenter.name}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={workCenter.name}
                    title="Comisión de seguridad e higiene"
                    description={
                        committee === null
                            ? 'Primer paso de NOM-037 en este centro: registra la Comisión y se crea el borrador de la lista de verificación.'
                            : 'Quienes revisan los lugares de teletrabajo de este centro.'
                    }
                    facts={committeeFacts(committee)}
                />
                <CommitteePanel
                    tenantSlug={tenantSlug}
                    workCenterId={workCenter.id}
                    committee={committee}
                    candidates={candidates}
                    canManage={canManage}
                />
            </div>
        </>
    );
}

CommitteePage.layout = {
    breadcrumbs: [{ title: 'NOM-037' }, { title: 'Comisión' }],
};
