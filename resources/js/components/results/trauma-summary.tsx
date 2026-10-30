import type { ScreeningCounts } from '@/types';

/** The Guía I of the campaign, counted. Only for those who channel people to care (num. 5.5). */
export default function TraumaSummary({ counts }: { counts: ScreeningCounts }) {
    return (
        <section className="grid gap-3 rounded-xl border bg-card p-6">
            <h2 className="text-xl font-semibold">
                Guía I: acontecimientos traumáticos severos
            </h2>
            <dl className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1">
                    <dt className="text-sm text-muted-foreground">
                        Contestaron
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {counts.answered}
                    </dd>
                </div>
                <div className="grid gap-1">
                    <dt className="text-sm text-muted-foreground">
                        Requieren valoración clínica
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {counts.requiring}
                    </dd>
                </div>
            </dl>
        </section>
    );
}
