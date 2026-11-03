import { Head, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import AckList from '@/components/disclosure/ack-list';
import DisclosureTabs from '@/components/disclosure/disclosure-tabs';
import DisseminationSummary from '@/components/disclosure/dissemination-summary';
import EmptyState from '@/components/humetria/empty-state';
import RecordsSection from '@/components/disclosure/records-section';
import PageHeader from '@/components/humetria/page-header';
import type { DisseminationProps } from '@/types';

export default function Dissemination({
    version,
    progress,
    counts,
    filters,
    people,
    today,
    publishedDay,
    records,
}: DisseminationProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-035' }, { title: 'Política y difusión' }],
    });

    return (
        <>
            <Head title="Difusión de la política" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Política y difusión"
                    title="Difusión de la política"
                    description={
                        version === null
                            ? 'Aquí ves quiénes confirmaron que leyeron la política, registras las hojas firmadas y recuerdas a quien falta.'
                            : `Versión ${version.version}, publicada el ${version.publishedOn}. Quién confirmó que la leyó y quién falta.`
                    }
                />
                <DisclosureTabs active="dissemination" />
                {version === null || progress === null || counts === null ? (
                    <EmptyState
                        icon={FileText}
                        title="Aún no hay una política publicada"
                    >
                        Cuando publiques la política, a las personas de tus
                        centros les llegará por correo y aquí verás quién la
                        confirmó.
                    </EmptyState>
                ) : (
                    <>
                        <DisseminationSummary
                            documentId={version.id}
                            version={null}
                            progress={progress}
                            counts={counts}
                            filters={filters}
                        />
                        <AckList
                            documentId={version.id}
                            version={null}
                            people={people}
                            filters={filters}
                            days={{ first: publishedDay ?? today, today }}
                        />
                    </>
                )}
                <RecordsSection records={records} today={today} />
            </div>
        </>
    );
}
