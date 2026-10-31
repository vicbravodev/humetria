import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ClipboardCheck,
    FileText,
    ListChecks,
    UserPlus,
    Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import PageHeader from '@/components/page-header';
import type { PageFact } from '@/components/page-header';
import CommitteePanel from '@/components/telework/committee-panel';
import { describeDate } from '@/lib/calendar-date';
import { show } from '@/routes/tenant/telework/checklist';
import { index as documentsIndex } from '@/routes/tenant/telework/documents';
import { index as reviewsIndex } from '@/routes/tenant/telework/reviews';
import { create } from '@/routes/tenant/telework/teleworkers';
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
    canEditChecklist,
    canReview,
}: {
    workCenter: { id: string; name: string };
    committee: Committee | null;
    /** Only for whoever manages the committee. */
    candidates?: CommitteePerson[];
    canManage: boolean;
    canEditChecklist: boolean;
    /** Whoever sits on this Comisión reviews the checklists people send. */
    canReview: boolean;
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
                >
                    {committee !== null && canManage && (
                        <Button variant="outline" asChild>
                            <Link
                                href={create({
                                    tenant: tenantSlug,
                                    workCenter: workCenter.id,
                                })}
                            >
                                <UserPlus aria-hidden />
                                Agregar persona
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" asChild>
                        <Link href={documentsIndex(tenantSlug)}>
                            <FileText aria-hidden />
                            Documentos
                        </Link>
                    </Button>
                    {canReview && (
                        <Button variant="outline" asChild>
                            <Link
                                href={reviewsIndex({
                                    tenant: tenantSlug,
                                    workCenter: workCenter.id,
                                })}
                            >
                                <ClipboardCheck aria-hidden />
                                Listas por revisar
                            </Link>
                        </Button>
                    )}
                    {committee !== null && (
                        <Button asChild>
                            <Link
                                href={show({
                                    tenant: tenantSlug,
                                    workCenter: workCenter.id,
                                })}
                            >
                                <ListChecks aria-hidden />
                                {canEditChecklist
                                    ? 'Editar la lista'
                                    : 'Ver la lista'}
                            </Link>
                        </Button>
                    )}
                </PageHeader>
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
