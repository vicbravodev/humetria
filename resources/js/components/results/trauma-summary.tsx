import { Link } from '@inertiajs/react';
import { HandHeart, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { screenings } from '@/routes/tenant/campaigns';
import type { ScreeningCounts } from '@/types';

/**
 * The Guía I of the campaign, counted, and the way to who needs care (pantallas: Eval-Cerrada). Only for those who
 * channel people (num. 5.5).
 */
export default function TraumaSummary({
    url,
    counts,
}: {
    url: Parameters<typeof screenings>[0];
    counts: ScreeningCounts;
}) {
    return (
        <section
            aria-labelledby="trauma-heading"
            className="flex flex-col gap-3.5 rounded-xl border bg-card p-6"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-risk-alto-bg text-risk-alto-fg">
                    <HandHeart aria-hidden className="size-5" />
                </span>
                <h2 id="trauma-heading" className="text-lg font-semibold">
                    Guía I · acontecimientos traumáticos
                </h2>
            </div>
            <p className="text-sm text-muted-foreground">
                {counts.requiring} de {counts.answered} personas que contestaron
                requieren valoración clínica. Solo las personas autorizadas ven
                quiénes son.
            </p>
            <Button asChild variant="outline" size="sm" className="w-fit">
                <Link href={screenings(url)}>
                    <Lock aria-hidden="true" />
                    Ver canalización
                </Link>
            </Button>
        </section>
    );
}
