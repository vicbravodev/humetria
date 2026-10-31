import type { ReactNode } from 'react';
import DossierChecklistBlock from '@/components/telework/dossier-checklist';
import DossierCommitteeBlock from '@/components/telework/dossier-committee';
import DossierDocumentBlock from '@/components/telework/dossier-document';
import DossierEquipmentBlock from '@/components/telework/dossier-equipment';
import DossierNoticesBlock from '@/components/telework/dossier-notices';
import DossierRoster from '@/components/telework/dossier-roster';
import DossierTrainings from '@/components/telework/dossier-trainings';
import DossierWorkplaces from '@/components/telework/dossier-workplaces';
import type { DossierData } from '@/types';

/** The evidence of a numeral of table 10.3 (the documents are found by their numeral). */
export default function DossierEvidenceBlock({
    numeral,
    data,
}: {
    numeral: string;
    data: DossierData;
}) {
    const places = <DossierWorkplaces workplaces={data.workplaces} />;
    const checklist = (
        <DossierChecklistBlock
            checklist={data.checklist}
            evidence={data.evidence}
        />
    );
    const byNumeral: Record<string, ReactNode> = {
        '5.1': <DossierRoster people={data.roster} />,
        '5.1.1': places,
        '5.5': places,
        '5.4 y 7': checklist,
        '5.5.1': checklist,
        '5.7': <DossierEquipmentBlock equipment={data.equipment} />,
        '5.9 y 8': (
            <DossierTrainings
                trainings={data.trainings}
                overdue={data.overdue}
            />
        ),
        '5.11': <DossierNoticesBlock notices={data.notices} />,
        '5.12': <DossierCommitteeBlock committee={data.committee} />,
    };

    return (
        byNumeral[numeral] ?? (
            <DossierDocumentBlock
                document={data.documents.find((d) => d.numeral === numeral)}
            />
        )
    );
}
