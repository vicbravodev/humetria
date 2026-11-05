import { Head, setLayoutProps } from '@inertiajs/react';
import DossierExtras from '@/components/seating/dossier-extras';
import DossierHeaderBlock from '@/components/seating/dossier-header';
import DossierSection from '@/components/seating/dossier-section';
import DossierToolbar from '@/components/seating/dossier-toolbar';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type {
    DossierLink,
    SeatingDossierDocument,
    SeatingDossierHeader,
    SeatingDossierRow,
} from '@/types';

/** The printable dossier of a center: one section per requirement with what exists or «Falta», then what stays in the app. */
export default function SeatingDossier({
    workCenterId,
    header,
    rows,
    levels,
    documents,
    links,
    fineNote,
}: {
    workCenterId: string;
    header: SeatingDossierHeader;
    rows: SeatingDossierRow[];
    levels: { low: number; medium: number; high: number };
    documents: SeatingDossierDocument[];
    links: DossierLink[];
    fineNote: string;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(
            tenantSlug,
            { id: workCenterId, name: header.workCenter },
            { title: 'Expediente' },
        ),
    });

    return (
        <>
            <Head title={`Expediente · ${header.workCenter}`} />
            <div className="mx-auto flex w-full max-w-[960px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <DossierToolbar workCenterId={workCenterId} />
                <DossierHeaderBlock header={header} />
                {rows.map((row) => (
                    <DossierSection key={row.key} row={row} />
                ))}
                <DossierExtras
                    workCenterId={workCenterId}
                    levels={levels}
                    documents={documents}
                    links={links}
                    fineNote={fineNote}
                />
            </div>
        </>
    );
}
