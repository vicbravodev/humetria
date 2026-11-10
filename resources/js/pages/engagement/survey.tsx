import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import SurveyDone from '@/components/engagement/survey/survey-done';
import SurveyRunner from '@/components/engagement/survey/survey-runner';
import SurveyShell from '@/components/engagement/survey/survey-shell';
import SurveyUnavailableNotice from '@/components/engagement/survey/survey-unavailable';
import { tokenOfPath } from '@/lib/engagement/replies';
import { forgetLink } from '@/lib/survey';
import type { EngagementSurveyProps } from '@/types';

export default function Survey(survey: EngagementSurveyProps) {
    const { token, unavailable, company } = survey;

    // A link that no longer works leaves nothing of it behind on the device.
    useEffect(() => {
        const link = tokenOfPath(window.location.pathname);

        if (token === null && link !== null) {
            forgetLink(window.localStorage, link);
        }
    }, [token]);

    return (
        <SurveyShell company={company}>
            <Head title="Encuesta" />
            {unavailable === 'done' && <SurveyDone />}
            {unavailable !== null && unavailable !== 'done' && (
                <SurveyUnavailableNotice reason={unavailable} />
            )}
            {unavailable === null && token !== null && (
                <SurveyRunner survey={survey} token={token} />
            )}
        </SurveyShell>
    );
}
