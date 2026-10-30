import { Head } from '@inertiajs/react';
import SurveyFlow from '@/components/campaigns/survey/survey-flow';
import SurveyShell from '@/components/campaigns/survey/survey-shell';
import SurveyUnavailableNotice from '@/components/campaigns/survey/survey-unavailable';
import type { SurveyInstrument, SurveyUnavailable } from '@/types';

export default function Survey({
    token,
    unavailable,
    company,
    closesOn,
    instruments,
}: {
    token: string | null;
    unavailable: SurveyUnavailable | null;
    company: string | null;
    closesOn: string | null;
    instruments: SurveyInstrument[];
}) {
    const next = instruments.find(
        (instrument) => instrument.status === 'pending',
    );

    return (
        <SurveyShell company={company}>
            <Head title="Evaluación" />
            {unavailable !== null ||
            company === null ||
            token === null ||
            next === undefined ? (
                <SurveyUnavailableNotice reason={unavailable ?? 'invalid'} />
            ) : (
                <SurveyFlow
                    // A new key once a guide is sent: the person is back at the list, the sent one marked.
                    key={next.key}
                    token={token}
                    company={company}
                    closesOn={closesOn}
                    instruments={instruments}
                    next={next}
                />
            )}
        </SurveyShell>
    );
}
