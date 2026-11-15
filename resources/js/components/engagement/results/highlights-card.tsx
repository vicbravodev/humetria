import TopicBar from '@/components/engagement/results/topic-bar';
import type { EngagementItem } from '@/types';

/** The questions answered best and worst: where the group is strong and where there is room to act, side by side. */
export default function HighlightsCard({
    strengths,
    opportunities,
    reference = null,
}: {
    strengths: EngagementItem[];
    opportunities: EngagementItem[];
    /** The company's engagement, as the mark of every bar. */
    reference?: number | null;
}) {
    const columns = [
        { title: 'Fortalezas', items: strengths },
        { title: 'Oportunidades', items: opportunities },
    ];

    return (
        <section aria-labelledby="highlights" className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
                <h2 id="highlights" className="text-xl font-semibold">
                    Fortalezas y oportunidades
                </h2>
                <p className="text-sm text-muted-foreground">
                    Las tres preguntas mejor y peor calificadas.
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                {columns.map((column) => (
                    <article
                        key={column.title}
                        className="flex flex-col gap-4 card p-5 sm:p-6"
                    >
                        <h3 className="text-lg font-semibold">
                            {column.title}
                        </h3>
                        {column.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Las preguntas con menos de 5 respuestas no se
                                muestran.
                            </p>
                        ) : (
                            column.items.map((item, index) => (
                                <TopicBar
                                    key={item.key}
                                    text={item.text}
                                    favorable={item.favorable}
                                    reference={reference}
                                    index={index}
                                />
                            ))
                        )}
                    </article>
                ))}
            </div>
        </section>
    );
}
