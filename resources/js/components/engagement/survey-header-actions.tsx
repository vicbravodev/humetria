import { Lock } from 'lucide-react';
import { useState } from 'react';
import CloseDialog from '@/components/engagement/close-dialog';
import DeleteDraftDialog from '@/components/engagement/delete-draft-dialog';
import RemindDialog from '@/components/engagement/remind-dialog';
import RowMenu from '@/components/humetria/row-menu';
import type { ClimateParticipation, ClimateSurveyCard } from '@/types';

/**
 * What RH may do with the survey in its state: throw a draft away; with one in progress, remind who is missing (the
 * primary action) and, under «Más», close it.
 */
export default function SurveyHeaderActions({
    survey,
    canManage,
    participation,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
    participation: ClimateParticipation | null;
}) {
    const [closing, setClosing] = useState(false);

    if (!canManage || survey.status === 'closed') {
        return null;
    }

    if (survey.status === 'draft') {
        return <DeleteDraftDialog surveyId={survey.id} />;
    }

    return (
        <>
            <RowMenu
                label={`Más acciones de ${survey.name}`}
                items={[
                    {
                        label: 'Cerrar encuesta',
                        icon: Lock,
                        onSelect: () => setClosing(true),
                        destructive: true,
                    },
                ]}
            />
            <CloseDialog
                surveyId={survey.id}
                open={closing}
                onOpenChange={setClosing}
            />
            {participation?.canRemind === true && (
                <RemindDialog
                    surveyId={survey.id}
                    pending={participation.pending}
                />
            )}
        </>
    );
}
