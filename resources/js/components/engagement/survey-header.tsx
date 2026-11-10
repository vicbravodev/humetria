import { CalendarDays } from 'lucide-react';
import SurveyHeaderActions from '@/components/engagement/survey-header-actions';
import ModeBadge from '@/components/engagement/pulse/mode-badge';
import SurveyStatusBadge from '@/components/engagement/survey-status-badge';
import PageHeader from '@/components/humetria/page-header';
import { deadlineText } from '@/lib/engagement/panel';
import type { ClimateParticipation, ClimateSurveyCard } from '@/types';

/**
 * The name and state of the survey (pantallas Eval-Borrador, Eval-Activa, Eval-Cerrada): a draft says what is left to do,
 * a launched one its last day.
 */
export default function SurveyHeader({
    survey,
    canManage,
    participation,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
    participation: ClimateParticipation | null;
}) {
    const deadline = deadlineText(survey);

    return (
        <PageHeader
            title={survey.name}
            status={
                <>
                    <SurveyStatusBadge
                        status={survey.status}
                        label={survey.statusLabel}
                    />
                    <ModeBadge mode={survey.mode} />
                </>
            }
            {...(deadline === undefined
                ? {
                      description: canManage
                          ? 'Revisa tres cosas y lanza. Nadie recibe nada hasta que lo hagas.'
                          : 'Todavía no se lanza: nadie ha recibido nada.',
                  }
                : { facts: [{ icon: CalendarDays, text: deadline }] })}
        >
            <SurveyHeaderActions
                survey={survey}
                canManage={canManage}
                participation={participation}
            />
        </PageHeader>
    );
}
