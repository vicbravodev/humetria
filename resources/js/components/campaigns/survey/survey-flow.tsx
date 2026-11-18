import { useState } from 'react';
import GuideList from '@/components/campaigns/survey/guide-list';
import GuideSent from '@/components/campaigns/survey/guide-sent';
import SurveyActions from '@/components/campaigns/survey/survey-actions';
import SurveyIntro from '@/components/campaigns/survey/survey-intro';
import SurveyRunner from '@/components/campaigns/survey/survey-runner';
import { Button } from '@/components/ui/button';
import { introContext, minutesLeft } from '@/lib/survey';
import type { IntroCopy } from '@/lib/survey';
import type { CampaignKind, SurveyInstrument } from '@/types';

/**
 * The norm's presentation with the person's guides (sent or pending), then the next pending guide. Remounted after
 * each guide is sent: then it opens on the confirmation of the guide just sent, never when the person comes back later.
 */
export default function SurveyFlow({
    token,
    company,
    kind,
    closesOn,
    copy,
    instruments,
    minGroup,
    next,
    justSent,
    onSent,
}: {
    token: string;
    company: string;
    kind: CampaignKind;
    closesOn: string | null;
    copy: IntroCopy;
    instruments: SurveyInstrument[];
    minGroup: number | null;
    next: SurveyInstrument;
    /** The guide the person sent on this page a moment ago, if any. */
    justSent: string | null;
    onSent: (instrument: string) => void;
}) {
    const sent = instruments.filter(
        (instrument) => instrument.status === 'completed',
    );
    const confirmed = sent.find((instrument) => instrument.key === justSent);
    const [view, setView] = useState<'sent' | 'intro' | 'guide'>(
        confirmed === undefined ? 'intro' : 'sent',
    );
    const pending = instruments.length - sent.length;

    if (view === 'guide') {
        return (
            <SurveyRunner
                token={token}
                instruments={instruments}
                instrument={next}
                noun={copy.noun}
                minGroup={minGroup}
                onBack={() => setView('intro')}
                onSent={onSent}
            />
        );
    }

    if (view === 'sent' && confirmed !== undefined) {
        return (
            <GuideSent
                sent={confirmed}
                next={next}
                done={sent.length}
                total={instruments.length}
                onContinue={() => setView('guide')}
                onLater={() => setView('intro')}
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
                    onClick={() => setView('guide')}
                >
                    {sent.length > 0
                        ? `Continuar con la ${copy.noun === 'guía' ? next.shortTitle : copy.noun}`
                        : 'Comenzar'}
                </Button>
            </SurveyActions>
        </div>
    );
}
