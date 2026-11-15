import { Check } from 'lucide-react';
import { useState } from 'react';
import SurveyIntro from '@/components/campaigns/survey/survey-intro';
import SurveyRunner from '@/components/campaigns/survey/survey-runner';
import { Button } from '@/components/ui/button';
import type { IntroCopy } from '@/lib/survey';
import type { SurveyInstrument } from '@/types';

/**
 * The norm's presentation with the person's guides (sent or pending), then the next pending guide. Remounted after
 * each guide is sent, so the person sees it marked "Enviada" before going on.
 */
export default function SurveyFlow({
    token,
    company,
    closesOn,
    copy,
    instruments,
    next,
}: {
    token: string;
    company: string;
    closesOn: string | null;
    copy: IntroCopy;
    instruments: SurveyInstrument[];
    next: SurveyInstrument;
}) {
    const [started, setStarted] = useState(false);
    const answered = instruments.some(
        (instrument) => instrument.status === 'completed',
    );

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
        <SurveyIntro company={company} closesOn={closesOn} copy={copy}>
            <ol className="overflow-hidden rounded-lg border bg-card">
                {instruments.map((instrument, index) => (
                    <li
                        key={instrument.key}
                        className="flex items-center gap-3 border-t px-4 py-3.5 first:border-t-0"
                    >
                        <span
                            aria-hidden="true"
                            className="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-secondary text-base font-medium text-muted-foreground tabular-nums"
                        >
                            {index + 1}
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="text-[1.0625rem] font-medium">
                                {instrument.shortTitle}
                            </span>
                            <span className="text-base text-muted-foreground">
                                {instrument.title}
                            </span>
                        </span>
                        {instrument.status === 'completed' ? (
                            <span className="flex shrink-0 items-center gap-1.5 text-base font-medium text-primary">
                                <Check aria-hidden="true" className="size-4" />
                                Enviada
                            </span>
                        ) : (
                            <span className="shrink-0 text-base text-muted-foreground">
                                Pendiente
                            </span>
                        )}
                    </li>
                ))}
            </ol>
            <Button
                size="lg"
                className="w-full rounded-[10px] text-[1.0625rem]"
                onClick={() => setStarted(true)}
            >
                {answered
                    ? `Continuar con la ${copy.noun === 'guía' ? next.shortTitle : copy.noun}`
                    : 'Comenzar'}
            </Button>
        </SurveyIntro>
    );
}
