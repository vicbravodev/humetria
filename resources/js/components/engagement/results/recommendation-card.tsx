import RecommendationBands from '@/components/engagement/results/recommendation-bands';
import RecommendationScale from '@/components/engagement/results/recommendation-scale';
import ProtectedCell from '@/components/humetria/protected-cell';
import { subtractionText } from '@/lib/engagement/results';
import { signedText } from '@/lib/engagement/trend';
import type { EngagementRecommendation } from '@/types';

/**
 * The recommendation index with its scale drawn and how it is calculated: how many would recommend the company as a
 * place to work against how many would not. The three shares show from five answers; the index only from thirty,
 * because one person changes it too much below.
 */
export default function RecommendationCard({
    recommendation,
}: {
    recommendation: EngagementRecommendation;
}) {
    const { bands, index, shown } = recommendation;
    const subtraction = subtractionText(bands, index);

    return (
        <section
            aria-labelledby="recommendation-index"
            className="flex min-w-0 flex-col gap-5 card p-5 sm:p-6"
        >
            <h2 id="recommendation-index" className="text-lg font-semibold">
                Índice de recomendación
            </h2>
            {!shown || bands === null ? (
                <div className="flex flex-col gap-2">
                    <ProtectedCell />
                    <p className="text-sm text-muted-foreground">
                        Menos de 5 respuestas: no se muestra.
                    </p>
                </div>
            ) : (
                <>
                    {index === null ? (
                        <p className="text-sm text-muted-foreground">
                            El índice se muestra desde 30 respuestas; antes, un
                            solo cambio lo movería demasiado.
                        </p>
                    ) : (
                        <>
                            <p className="flex flex-wrap items-baseline gap-x-3">
                                <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                                    {signedText(index)}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    en una escala de −100 a 100
                                </span>
                            </p>
                            <RecommendationScale index={index} />
                        </>
                    )}
                    <RecommendationBands bands={bands} />
                    {subtraction !== null && (
                        <p className="text-sm text-muted-foreground">
                            {subtraction}
                        </p>
                    )}
                </>
            )}
        </section>
    );
}
