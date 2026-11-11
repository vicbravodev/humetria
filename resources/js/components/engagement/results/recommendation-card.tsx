import { favorableText } from '@/lib/engagement/format';
import type { EngagementRecommendation } from '@/types';

const BANDS = [
    {
        key: 'detractors',
        label: 'No lo recomendarían (0 a 6)',
        fill: 'bg-risk-alto',
    },
    { key: 'passives', label: 'Indiferentes (7 y 8)', fill: 'bg-risk-medio' },
    {
        key: 'promoters',
        label: 'Lo recomendarían (9 y 10)',
        fill: 'bg-risk-bajo',
    },
] as const;

/**
 * The recommendation index: how many would recommend the company as a place to work against how many would not. The
 * three shares show from five answers; the index itself only from thirty, because one person changes it too much below.
 */
export default function RecommendationCard({
    recommendation,
}: {
    recommendation: EngagementRecommendation;
}) {
    const { bands, index, shown } = recommendation;

    return (
        <section
            aria-label="Índice de recomendación"
            className="flex flex-col gap-4 card p-5 sm:p-6"
        >
            <h2 className="text-lg font-semibold">Índice de recomendación</h2>
            {!shown || bands === null ? (
                <p className="text-muted-foreground">{favorableText(null)}.</p>
            ) : (
                <>
                    <p className="text-[34px] leading-10 font-semibold tracking-[-0.02em] tabular-nums">
                        {index === null ? '—' : index}
                    </p>
                    {index === null && (
                        <p className="text-sm text-muted-foreground">
                            El índice se muestra desde 30 respuestas; antes, un
                            solo cambio lo movería demasiado.
                        </p>
                    )}
                    <div
                        role="img"
                        aria-label={BANDS.map(
                            (band) => `${band.label} ${bands[band.key]} %`,
                        ).join(', ')}
                        className="flex h-3.5 w-full gap-0.5 overflow-hidden rounded-full"
                    >
                        {BANDS.map((band) => (
                            <span
                                key={band.key}
                                className={`block h-full min-w-0.5 ${band.fill}`}
                                style={{ width: `${bands[band.key]}%` }}
                            />
                        ))}
                    </div>
                    <ul className="flex flex-wrap gap-x-4 gap-y-1.5 text-caption">
                        {BANDS.map((band) => (
                            <li
                                key={band.key}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    aria-hidden
                                    className={`size-2 rounded-full ${band.fill}`}
                                />
                                {band.label}
                                <strong className="font-semibold">
                                    {bands[band.key]} %
                                </strong>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}
