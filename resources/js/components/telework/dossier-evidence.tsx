import type { ReactNode } from 'react';
import DossierChecklistBlock from '@/components/telework/dossier-checklist';
import DossierCommitteeBlock from '@/components/telework/dossier-committee';
import DossierDocumentBlock from '@/components/telework/dossier-document';
import DossierEquipmentBlock from '@/components/telework/dossier-equipment';
import DossierFixedPlaces from '@/components/telework/dossier-fixed-places';
import DossierNoticesBlock from '@/components/telework/dossier-notices';
import DossierRoster from '@/components/telework/dossier-roster';
import DossierTrainings from '@/components/telework/dossier-trainings';
import DossierWorkplaces from '@/components/telework/dossier-workplaces';
import { dossierBlock, inapplicableText } from '@/lib/telework/dossier';
import type { DossierData, DossierRow } from '@/types';

/** The evidence of a row of table 10.3; a row that does not apply says so instead of printing an empty block. */
export default function DossierEvidenceBlock({
    row,
    data,
}: {
    row: DossierRow;
    data: DossierData;
}) {
    if (row.state === 'not_applicable') {
        return (
            <p className="text-[15px] leading-[22px] text-muted-foreground">
                {inapplicableText(row.numeral)}
            </p>
        );
    }

    const blocks: Record<ReturnType<typeof dossierBlock>, ReactNode> = {
        roster: <DossierRoster people={data.roster} />,
        workplaces: <DossierWorkplaces workplaces={data.workplaces} />,
        fixed: <DossierFixedPlaces workplaces={data.workplaces} />,
        checklist: (
            <DossierChecklistBlock
                checklist={data.checklist}
                evidence={data.evidence}
            />
        ),
        equipment: <DossierEquipmentBlock equipment={data.equipment} />,
        trainings: (
            <DossierTrainings
                trainings={data.trainings}
                overdue={data.overdue}
            />
        ),
        notices: <DossierNoticesBlock notices={data.notices} />,
        committee: <DossierCommitteeBlock committee={data.committee} />,
        document: (
            <DossierDocumentBlock
                document={data.documents.find((d) => d.numeral === row.numeral)}
            />
        ),
    };

    return blocks[dossierBlock(row.numeral)];
}
