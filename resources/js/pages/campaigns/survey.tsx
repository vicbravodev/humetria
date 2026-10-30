import { Head } from '@inertiajs/react';
import SurveyIntro from '@/components/campaigns/survey/survey-intro';
import SurveyShell from '@/components/campaigns/survey/survey-shell';
import SurveyUnavailableNotice from '@/components/campaigns/survey/survey-unavailable';
import type { SurveyInstrument, SurveyUnavailable } from '@/types';

export default function Survey({
    unavailable,
    company,
    closesOn,
    instruments,
}: {
    unavailable: SurveyUnavailable | null;
    company: string | null;
    closesOn: string | null;
    instruments: SurveyInstrument[];
}) {
    return (
        <SurveyShell company={company}>
            <Head title="Evaluación" />
            {unavailable !== null || company === null ? (
                <SurveyUnavailableNotice reason={unavailable ?? 'invalid'} />
            ) : (
                <SurveyIntro company={company} closesOn={closesOn}>
                    <ul className="flex flex-col gap-3">
                        {instruments.map((instrument) => (
                            <li
                                key={instrument.key}
                                className="flex items-center justify-between gap-3 rounded-lg border bg-card p-4"
                            >
                                <span className="text-lg">
                                    {instrument.title}
                                </span>
                                <span className="text-muted-foreground">
                                    {instrument.status === 'completed'
                                        ? 'Contestada'
                                        : 'Pendiente'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </SurveyIntro>
            )}
        </SurveyShell>
    );
}
