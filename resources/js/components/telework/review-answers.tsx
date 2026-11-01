import ReviewAnswerGroup from '@/components/telework/review-answer-group';
import ReviewPhotos from '@/components/telework/review-photos';
import type { ReviewAnswers } from '@/types';

/** Everything the person answered: what flags a risk first, then the rest, then what only describes the place. */
export default function ReviewAnswersView({
    tenantSlug,
    answers,
    specialNeeds,
}: {
    tenantSlug: string;
    answers: ReviewAnswers;
    specialNeeds: string | null;
}) {
    return (
        <div className="flex flex-col gap-6">
            <ReviewAnswerGroup
                tenantSlug={tenantSlug}
                title="Con riesgo"
                description="Respuestas desfavorables, con su nota y sus fotos."
                answers={answers.unfavorable}
                flagged
            />
            {answers.generalPhotos.length > 0 && (
                <section className="grid gap-3 rounded-xl border bg-card p-5">
                    <h2 className="text-lg font-semibold">Fotos del lugar</h2>
                    <ReviewPhotos
                        tenantSlug={tenantSlug}
                        photos={answers.generalPhotos}
                    />
                </section>
            )}
            <ReviewAnswerGroup
                tenantSlug={tenantSlug}
                title="Sin riesgo"
                description="Respuestas favorables, no aplica y las que no se contestaron."
                answers={answers.other}
            />
            <ReviewAnswerGroup
                tenantSlug={tenantSlug}
                title="Informativas"
                description="Describen el lugar; no cuentan como riesgo."
                answers={answers.informative}
            />
            {specialNeeds !== null && (
                <section className="grid gap-1 rounded-xl border bg-card p-5">
                    <h2 className="text-lg font-semibold">
                        Necesidad especial
                    </h2>
                    <p className="text-[15px] leading-[22px]">{specialNeeds}</p>
                </section>
            )}
        </div>
    );
}
