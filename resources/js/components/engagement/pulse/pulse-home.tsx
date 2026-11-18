import HowWeAreCard from '@/components/engagement/pulse/how-we-are-card';
import PulseFirstTime from '@/components/engagement/pulse/pulse-first-time';
import PulseHomeHeader from '@/components/engagement/pulse/pulse-home-header';
import ScheduleCard from '@/components/engagement/pulse/schedule-card';
import SurveyCard from '@/components/engagement/survey-card';
import SurveyHistory from '@/components/engagement/survey-history';
import { homeState } from '@/lib/engagement/panel';
import type { EngagementHome } from '@/types';

/**
 * The home of Pulso (pantalla Pulso · Inicio): the next pulse, or the one in progress, beside how the engagement moves,
 * then the history. A first time only when there is no pulse at all and none is scheduled.
 */
export default function PulseHome({
    current,
    surveys,
    canManage,
    modes,
    schedule,
    frequencies,
    howWeAre,
    minGroup,
}: EngagementHome) {
    const state = homeState({
        current: current !== null,
        closed: surveys.total,
        scheduled: schedule !== null,
    });
    const manageModes = canManage ? modes : null;
    const scheduleCard = (
        <ScheduleCard
            schedule={schedule}
            frequencies={frequencies ?? []}
            modes={modes ?? []}
            canManage={canManage}
        />
    );

    return (
        <>
            <PulseHomeHeader
                schedule={schedule}
                createWith={
                    state === 'scheduled' || state === 'history'
                        ? manageModes
                        : null
                }
                hasHistory={surveys.total > 0}
                minGroup={minGroup}
            />
            {state === 'first-time' ? (
                <>
                    <PulseFirstTime modes={manageModes} />
                    {scheduleCard}
                </>
            ) : (
                <div className="grid items-start gap-6 lg:grid-cols-[2fr_1fr]">
                    <div className="flex min-w-0 flex-col gap-6">
                        {current !== null && <SurveyCard survey={current} />}
                        {scheduleCard}
                    </div>
                    <HowWeAreCard points={howWeAre ?? []} minGroup={minGroup} />
                </div>
            )}
            <SurveyHistory surveys={surveys} />
        </>
    );
}
