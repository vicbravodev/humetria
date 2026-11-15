import ModeBadge from '@/components/engagement/pulse/mode-badge';
import SurveyCardActions from '@/components/engagement/survey-card-actions';
import SurveyStatusBadge from '@/components/engagement/survey-status-badge';
import ArcMeter from '@/components/humetria/arc-meter';
import { answeredText, deadlineText } from '@/lib/engagement/panel';
import type { ClimateSurveyCard } from '@/types';

/**
 * The survey in progress as the protagonist of the home (pantalla Clima · Inicio): its share inside the arch with the
 * count below, its last day, «Ver avance» as the primary action and, for who manages it, the reminder.
 */
export default function SurveyCard({
    survey,
    canManage = false,
}: {
    survey: ClimateSurveyCard;
    canManage?: boolean;
}) {
    const answered = answeredText(survey);

    return (
        <article className="flex flex-col gap-6 card p-5 sm:flex-row sm:items-center sm:gap-10 sm:p-7">
            {survey.invited !== null && survey.answered !== null && (
                <div className="flex flex-col items-center gap-1 self-center">
                    <ArcMeter
                        value={survey.answered}
                        max={survey.invited}
                        label={answered}
                        showPercent
                    />
                    <p className="text-sm text-muted-foreground">{answered}</p>
                </div>
            )}
            <div className="flex min-w-0 flex-1 flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-xl leading-7 font-semibold">
                        {survey.name}
                    </h2>
                    <SurveyStatusBadge
                        status={survey.status}
                        label={survey.statusLabel}
                    />
                    <ModeBadge mode={survey.mode} />
                </div>
                <p className="text-muted-foreground">
                    {deadlineText(survey) ??
                        `${answered} Revisa el borrador y lánzalo para invitar a las personas.`}
                </p>
                <SurveyCardActions survey={survey} canManage={canManage} />
            </div>
        </article>
    );
}
