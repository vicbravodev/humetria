import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AckCommittee from '@/components/telework/ack-committee';
import AckPanelConfirm from '@/components/telework/ack-panel-confirm';
import AckPaperDialog from '@/components/telework/ack-paper-dialog';
import AckPeopleList from '@/components/telework/ack-people-list';
import AckSummary from '@/components/telework/ack-summary';
import PageHeader from '@/components/page-header';
import { useChosenPeople } from '@/hooks/use-chosen-people';
import { describeDate } from '@/lib/calendar-date';
import type {
    AckCommitteeMember,
    AckDocument,
    AckFilters,
    AckPerson,
    AckProgress,
    Paginated,
} from '@/types';

export default function Acknowledgements({
    document,
    progress,
    people,
    committee,
    counts,
    filters,
    canManage,
    canAcknowledge,
}: {
    document: AckDocument;
    progress: AckProgress;
    people: Paginated<AckPerson>;
    committee: AckCommitteeMember[];
    counts: Record<AckFilters['status'], number>;
    filters: AckFilters;
    canManage: boolean;
    canAcknowledge: boolean;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    const choice = useChosenPeople(document.id);
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037' },
            { title: 'Documentos' },
            { title: 'Confirmaciones' },
        ],
    });

    return (
        <>
            <Head title={`Confirmaciones · ${document.title}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption={document.workCenter}
                    title={document.title}
                    description={`Publicada el ${describeDate(document.publishedOn) ?? document.publishedOn}. Aquí ves quién confirmó que la leyó.`}
                >
                    {canAcknowledge && (
                        <AckPanelConfirm
                            tenantSlug={tenantSlug}
                            documentId={document.id}
                        />
                    )}
                    {canManage && (
                        <AckPaperDialog
                            tenantSlug={tenantSlug}
                            documentId={document.id}
                            chosen={choice.chosen}
                            onDone={choice.clear}
                        />
                    )}
                </PageHeader>
                <AckSummary
                    tenantSlug={tenantSlug}
                    documentId={document.id}
                    progress={progress}
                    counts={counts}
                    filters={filters}
                />
                <AckPeopleList
                    tenantSlug={tenantSlug}
                    documentId={document.id}
                    people={people}
                    filters={filters}
                    choice={choice}
                    canManage={canManage}
                />
                <AckCommittee members={committee} />
            </div>
        </>
    );
}
