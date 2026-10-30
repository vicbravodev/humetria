import AnswersByDayCard from '@/components/campaigns/answers-by-day-card';
import AreaParticipationCard from '@/components/campaigns/area-participation-card';
import CampaignPeopleSection from '@/components/campaigns/campaign-people-section';
import ProgressCard from '@/components/campaigns/progress-card';
import SendingProgress from '@/components/campaigns/sending-progress';
import Notice from '@/components/humetria/notice';
import { progressNote } from '@/lib/campaigns';
import type {
    AnswersByDay,
    AreaParticipation,
    CampaignDetail,
    CampaignSending,
    InstrumentProgress,
    PeoplePage,
} from '@/types';

/**
 * A running evaluation (pantalla Eval-Activa): how far it has come, by day and by area, whether the Guía III sample is
 * met (III.2 c), and, for those who manage it, who answered.
 */
export default function CampaignFollowUp({
    route,
    campaign,
    instruments,
    people,
    sending,
    areas,
    answersByDay,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    campaign: CampaignDetail;
    instruments: InstrumentProgress[];
    people: PeoplePage | null;
    sending: CampaignSending | null;
    areas: AreaParticipation[] | null;
    answersByDay: AnswersByDay | null;
}) {
    const progress = campaign.progress;
    const sampleShort =
        progress !== null &&
        progress.againstSample &&
        progress.completed < progress.target;

    return (
        <>
            {sampleShort && (
                <Notice>
                    <strong>
                        {progressNote(progress)} para el mínimo de la muestra
                    </strong>{' '}
                    que pide la norma ({progress.target}). Si alguien no
                    contestará, sortea reemplazos.
                </Notice>
            )}
            <section aria-label="Avance" className="grid gap-5 md:grid-cols-3">
                {progress !== null && (
                    <ProgressCard
                        progress={progress}
                        instruments={instruments}
                    />
                )}
                {answersByDay !== null && (
                    <AnswersByDayCard answers={answersByDay} />
                )}
            </section>
            {areas !== null && areas.length > 0 && (
                <AreaParticipationCard route={route} areas={areas} />
            )}
            {people !== null && (
                <CampaignPeopleSection
                    route={route}
                    active={campaign.status === 'active'}
                    people={people}
                    instruments={instruments}
                />
            )}
            {sending !== null && <SendingProgress sending={sending} />}
        </>
    );
}
