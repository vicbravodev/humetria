import { Head, setLayoutProps } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import ScheduleCard from '@/components/engagement/pulse/schedule-card';
import ModePicker from '@/components/engagement/pulse/mode-picker';
import CreateSurveyButton from '@/components/engagement/create-survey-button';
import SurveyCard from '@/components/engagement/survey-card';
import SurveyHistory from '@/components/engagement/survey-history';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { useEngagementKind } from '@/hooks/use-engagement-routes';
import { homeCopy } from '@/lib/engagement/panel';
import type {
    ClimateSurveyCard,
    ClimateSurveyHistoryRow,
    Paginated,
    PulseModeOption,
    PulseSchedule,
} from '@/types';

/**
 * The home of Clima or Pulso (pantalla NOM035): the survey in progress, or the next step when there is none, and the
 * history of the closed ones. Only counts: RH never sees who answered.
 */
export default function EngagementIndex({
    current,
    surveys,
    canManage,
    modes,
    schedule,
    frequencies,
}: {
    current: ClimateSurveyCard | null;
    surveys: Paginated<ClimateSurveyHistoryRow>;
    canManage: boolean;
    modes: PulseModeOption[] | null;
    schedule: PulseSchedule | null;
    frequencies: PulseModeOption[] | null;
}) {
    const copy = homeCopy(useEngagementKind(), surveys.total);

    setLayoutProps({ breadcrumbs: [{ title: copy.title }] });

    return (
        <>
            <Head title={copy.title} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader title={copy.title} description={copy.description} />
                {current !== null ? (
                    <SurveyCard survey={current} />
                ) : (
                    <EmptyState
                        icon={Gauge}
                        title={copy.emptyTitle}
                        {...(canManage
                            ? {
                                  action:
                                      modes === null ? (
                                          <CreateSurveyButton
                                              label={copy.createLabel}
                                          />
                                      ) : (
                                          <ModePicker
                                              modes={modes}
                                              label={copy.createLabel}
                                          />
                                      ),
                              }
                            : {})}
                    >
                        {copy.emptyBody}
                    </EmptyState>
                )}
                {modes !== null && frequencies !== null && (
                    <ScheduleCard
                        schedule={schedule}
                        frequencies={frequencies}
                        modes={modes}
                        canManage={canManage}
                    />
                )}
                <SurveyHistory surveys={surveys} />
            </div>
        </>
    );
}
