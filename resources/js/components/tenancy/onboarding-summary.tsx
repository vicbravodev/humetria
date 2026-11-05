import OnboardingTag from '@/components/tenancy/onboarding-tag';
import { Button } from '@/components/ui/button';
import {
    answered,
    APPLIES,
    createLabel,
    GUIDES,
    seatingTag,
    teleworkTag,
} from '@/lib/onboarding';
import type { CenterAnswers, OnboardingCenter } from '@/lib/onboarding';

/** «Lo que te toca» (board V3-Onboarding): each standard of each center as it is answered, and the button that creates the routes. */
export default function OnboardingSummary({
    centers,
    answers,
    processing,
    onCreate,
}: {
    centers: OnboardingCenter[];
    answers: Record<string, CenterAnswers>;
    processing: boolean;
    onCreate: () => void;
}) {
    const all = centers.map((center) => answers[center.id] ?? center);

    return (
        <aside
            aria-labelledby="summary"
            aria-live="polite"
            className="flex flex-col gap-3.5 card p-5 md:p-6"
        >
            <p id="summary" className="text-sm font-semibold text-primary">
                Lo que te toca
            </p>
            {centers.map((center) => {
                const answer = answers[center.id] ?? center;
                const rows = [
                    { norm: `NOM-035 · ${GUIDES[center.range]}`, tag: APPLIES },
                    { norm: 'NOM-037 · teletrabajo', tag: teleworkTag(answer) },
                    {
                        norm: 'Ley Silla · trabajo de pie',
                        tag: seatingTag(answer),
                    },
                ];

                return (
                    <div key={center.id} className="flex flex-col pb-1.5">
                        <p className="font-semibold">{center.name}</p>
                        {rows.map((row) => (
                            <div
                                key={row.norm}
                                className="flex items-center justify-between gap-3 border-t py-2.5 text-meta"
                            >
                                <span>{row.norm}</span>
                                <OnboardingTag tag={row.tag} />
                            </div>
                        ))}
                    </div>
                );
            })}
            <p className="text-sm text-muted-foreground">
                Donde no aplica, guardamos una constancia con fecha para tu
                expediente.
            </p>
            <Button
                size="lg"
                onClick={onCreate}
                disabled={
                    processing || !all.every((center) => answered(center))
                }
            >
                {createLabel(all)}
            </Button>
        </aside>
    );
}
