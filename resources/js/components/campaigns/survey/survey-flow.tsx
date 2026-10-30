import { useState } from 'react';
import SurveyIntro from '@/components/campaigns/survey/survey-intro';
import SurveyRunner from '@/components/campaigns/survey/survey-runner';
import { Button } from '@/components/ui/button';
import type { SurveyInstrument } from '@/types';

/**
 * The norm's presentation with the person's guides (sent or pending), then the next pending guide. Remounted after
 * each guide is sent, so the person sees it marked "Enviada" before going on.
 */
export default function SurveyFlow({
    token,
    company,
    closesOn,
    instruments,
    next,
}: {
    token: string;
    company: string;
    closesOn: string | null;
    instruments: SurveyInstrument[];
    next: SurveyInstrument;
}) {
    const [started, setStarted] = useState(false);
    const answered = instruments.some(
        (instrument) => instrument.status === 'completed',
    );

    if (started) {
        return <SurveyRunner token={token} instrument={next} />;
    }

    return (
        <SurveyIntro company={company} closesOn={closesOn}>
            <ul className="flex flex-col gap-3">
                {instruments.map((instrument) => (
                    <li
                        key={instrument.key}
                        className="flex items-center justify-between gap-3 rounded-lg border bg-card p-4"
                    >
                        <span className="text-lg">
                            {instrument.shortTitle}: {instrument.title}
                        </span>
                        <span className="shrink-0 text-muted-foreground">
                            {instrument.status === 'completed'
                                ? 'Enviada'
                                : 'Pendiente'}
                        </span>
                    </li>
                ))}
            </ul>
            <Button
                size="lg"
                className="h-14 text-lg"
                onClick={() => setStarted(true)}
            >
                {answered ? `Continuar con la ${next.shortTitle}` : 'Comenzar'}
            </Button>
        </SurveyIntro>
    );
}
