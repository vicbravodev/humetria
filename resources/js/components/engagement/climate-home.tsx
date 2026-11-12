import { Gauge } from 'lucide-react';
import CreateSurveyButton from '@/components/engagement/create-survey-button';
import SurveyCard from '@/components/engagement/survey-card';
import SurveyHistory from '@/components/engagement/survey-history';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { homeCopy } from '@/lib/engagement/panel';
import type { EngagementHome } from '@/types';

/** The home of Clima: the survey in progress, or the next step when there is none, and the history of the closed ones. */
export default function ClimateHome({
    current,
    surveys,
    canManage,
}: EngagementHome) {
    const copy = homeCopy('climate', surveys.total);

    return (
        <>
            <PageHeader title={copy.title} description={copy.description} />
            {current !== null ? (
                <SurveyCard survey={current} />
            ) : (
                <EmptyState
                    icon={Gauge}
                    title={copy.emptyTitle}
                    {...(canManage
                        ? {
                              action: (
                                  <CreateSurveyButton
                                      label={copy.createLabel}
                                  />
                              ),
                          }
                        : {})}
                >
                    {copy.emptyBody}
                </EmptyState>
            )}
            <SurveyHistory surveys={surveys} />
        </>
    );
}
