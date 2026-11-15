import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import SurveyDone from '@/components/campaigns/survey/survey-done';
import SurveyFlow from '@/components/campaigns/survey/survey-flow';
import SurveyShell from '@/components/campaigns/survey/survey-shell';
import SurveyUnavailableNotice from '@/components/campaigns/survey/survey-unavailable';
import { forgetLink, introCopy, tokenFromPath } from '@/lib/survey';
import type {
    CampaignKind,
    SurveyInstrument,
    SurveyUnavailable,
} from '@/types';

export default function Survey({
    token,
    unavailable,
    company,
    kind,
    closesOn,
    instruments,
}: {
    token: string | null;
    unavailable: SurveyUnavailable | null;
    company: string | null;
    /** Which campaign the link belongs to; none when it opens nothing. */
    kind: CampaignKind | null;
    closesOn: string | null;
    instruments: SurveyInstrument[];
}) {
    const next = instruments.find(
        (instrument) => instrument.status === 'pending',
    );
    const copy = introCopy(kind ?? 'nom035_evaluation');
    const usable = unavailable === null && next !== undefined;

    // A link that no longer works leaves nothing of it behind on the device.
    useEffect(() => {
        const link = tokenFromPath(window.location.pathname);

        if (!usable && link !== null) {
            forgetLink(window.localStorage, link);
        }
    }, [usable]);

    if (unavailable === 'done') {
        return (
            <SurveyShell company={company}>
                <Head title={copy.headTitle} />
                <SurveyDone kind={kind} />
            </SurveyShell>
        );
    }

    return (
        <SurveyShell company={company}>
            <Head title={copy.headTitle} />
            {unavailable !== null ||
            company === null ||
            token === null ||
            next === undefined ? (
                <div className="w-full max-w-2xl">
                    <SurveyUnavailableNotice
                        reason={unavailable ?? 'invalid'}
                    />
                </div>
            ) : (
                <SurveyFlow
                    // A new key once a guide is sent: the person is back at the list, the sent one marked.
                    key={next.key}
                    token={token}
                    company={company}
                    closesOn={closesOn}
                    copy={copy}
                    instruments={instruments}
                    next={next}
                />
            )}
        </SurveyShell>
    );
}
