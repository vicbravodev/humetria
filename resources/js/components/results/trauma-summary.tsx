import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { screenings } from '@/routes/tenant/campaigns';
import type { ScreeningCounts } from '@/types';

/** The Guía I of the campaign, counted, and the way to who needs care. Only for those who channel people (num. 5.5). */
export default function TraumaSummary({
    url,
    counts,
}: {
    url: Parameters<typeof screenings>[0];
    counts: ScreeningCounts;
}) {
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
            <Button asChild variant="outline" className="w-fit">
                <Link href={screenings(url)}>Ver quiénes la requieren</Link>
            </Button>
        </section>
    );
}
