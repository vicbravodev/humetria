import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import DossierHeaderBlock from '@/components/telework/dossier-header';
import DossierSection from '@/components/telework/dossier-section';
import DossierToolbar from '@/components/telework/dossier-toolbar';
import { index } from '@/routes/tenant/telework';
import type {
    DossierChecklist,
    DossierCommittee,
    DossierDocument,
    DossierEquipment,
    DossierEvidence,
    DossierHeader,
    DossierNotices,
    DossierOverdue,
    DossierPerson,
    DossierRow,
    DossierTraining,
    DossierWorkplace,
} from '@/types';

/** The printable dossier of a center: one section per row of table 10.3, in order, with its evidence or «Falta». */
export default function Dossier({
    header,
    rows,
    ...data
}: {
    header: DossierHeader;
    rows: DossierRow[];
    roster: DossierPerson[];
    workplaces: DossierWorkplace[];
    checklist: DossierChecklist | null;
    documents: DossierDocument[];
    equipment: DossierEquipment[];
    trainings: DossierTraining[];
    overdue: DossierOverdue[];
    notices: DossierNotices;
    committee: DossierCommittee | null;
    evidence: DossierEvidence;
    workCenterId: string;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037', href: index(tenantSlug) },
            { title: header.workCenter },
            { title: 'Expediente' },
        ],
    });

    return (
        <>
            <Head title={`Expediente · ${header.workCenter}`} />
            <div className="mx-auto flex w-full max-w-[960px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <DossierToolbar
                    tenantSlug={tenantSlug}
                    workCenterId={data.workCenterId}
                />
                <DossierHeaderBlock header={header} />
                {rows.map((row) => (
                    <DossierSection key={row.numeral} row={row} data={data} />
                ))}
            </div>
        </>
    );
}
