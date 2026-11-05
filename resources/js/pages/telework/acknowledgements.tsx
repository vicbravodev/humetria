import { Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import AckCommittee from '@/components/telework/ack-committee';
import AckPanelConfirm from '@/components/telework/ack-panel-confirm';
import AckPaperDialog from '@/components/telework/ack-paper-dialog';
import AckPeopleList from '@/components/telework/ack-people-list';
import AckSummary from '@/components/telework/ack-summary';
import PageHeader from '@/components/humetria/page-header';
import { useChosenPeople } from '@/hooks/use-chosen-people';
import { describeDate } from '@/lib/calendar-date';
import { liveAll } from '@/lib/telework/acknowledgements';
import type {
    AckAllMatching,
    AckCommitteeMember,
    AckDocument,
    AckFilters,
    AckPerson,
    AckProgress,
    Paginated,
} from '@/types';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as documentsIndex } from '@/routes/tenant/telework/documents';
import { teleworkCrumbs } from '@/lib/telework/crumbs';

export default function Acknowledgements({
    document,
    progress,
    today,
    people,
    committee,
    counts,
    filters,
    canManage,
    canAcknowledge,
}: {
    document: AckDocument;
    progress: AckProgress;
    today: string;
    people: Paginated<AckPerson>;
    committee: AckCommitteeMember[];
    counts: Record<AckFilters['status'], number>;
    filters: AckFilters;
    canManage: boolean;
    canAcknowledge: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const choice = useChosenPeople(document.id);
    const [chosenAll, setAll] = useState<AckAllMatching | null>(null);
    const all = liveAll(chosenAll, filters);
    const clear = () => {
        setAll(null);
        choice.clear();
    };
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(
            tenantSlug,
            null,
            { title: 'Documentos', href: documentsIndex(tenantSlug) },
            { title: 'Confirmaciones' },
        ),
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
                        <AckPanelConfirm documentId={document.id} />
                    )}
                    {canManage && (
                        <AckPaperDialog
                            documentId={document.id}
                            chosen={choice.chosen}
                            all={all}
                            days={{ first: document.publishedOn, today }}
                            onDone={clear}
                        />
                    )}
                </PageHeader>
                <AckSummary
                    documentId={document.id}
                    progress={progress}
                    counts={counts}
                    filters={filters}
                />
                <AckPeopleList
                    documentId={document.id}
                    people={people}
                    filters={filters}
                    choice={choice}
                    all={all}
                    onAll={setAll}
                    onClear={clear}
                    canManage={canManage}
                />
                <AckCommittee members={committee} />
            </div>
        </>
    );
}
