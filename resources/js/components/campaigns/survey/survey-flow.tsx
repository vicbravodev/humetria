import { useState } from 'react';
import GuideList from '@/components/campaigns/survey/guide-list';
import SurveyActions from '@/components/campaigns/survey/survey-actions';
import SurveyIntro from '@/components/campaigns/survey/survey-intro';
import SurveyRunner from '@/components/campaigns/survey/survey-runner';
import { Button } from '@/components/ui/button';
import { introContext, minutesLeft } from '@/lib/survey';
import type { IntroCopy } from '@/lib/survey';
import type { CampaignKind, SurveyInstrument } from '@/types';

/**
 * The norm's presentation with the person's guides (sent or pending), then the next pending guide. Remounted after
 * each guide is sent, so the person sees it marked "Enviada" before going on.
 */
export default function SurveyFlow({
    token,
    company,
    kind,
    closesOn,
    copy,
    instruments,
    next,
}: {
    token: string;
    company: string;
    kind: CampaignKind;
    closesOn: string | null;
    copy: IntroCopy;
    instruments: SurveyInstrument[];
    next: SurveyInstrument;
}) {
    const [started, setStarted] = useState(false);
    const answered = instruments.some(
        (instrument) => instrument.status === 'completed',
    );
    const pending = instruments.filter(
        (instrument) => instrument.status === 'pending',
    ).length;

    if (started) {
        return (
            <SurveyRunner
                token={token}
                instruments={instruments}
                instrument={next}
                noun={copy.noun}
                onBack={() => setStarted(false)}
            />
        );
    }

    return (
        <div className="flex w-full max-w-2xl flex-col gap-5">
            <SurveyIntro
                company={company}
                context={introContext(kind, instruments.length)}
                closesOn={closesOn}
                minutes={{
                    total: minutesLeft(instruments),
                    together: pending > 1,
                }}
                copy={copy}
            >
                <GuideList instruments={instruments} />
            </SurveyIntro>
            <SurveyActions>
                <Button
                    size="lg"
                    className="h-14 w-full rounded-[10px] text-[1.0625rem]"
                    onClick={() => setStarted(true)}
                >
                    {answered
                        ? `Continuar con la ${copy.noun === 'guía' ? next.shortTitle : copy.noun}`
                        : 'Comenzar'}
                </Button>
            </SurveyActions>
        </div>
    );
}
