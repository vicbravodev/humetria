import { Link } from '@inertiajs/react';
import { HandHeart, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { screenings } from '@/routes/tenant/campaigns';
import { cn } from '@/lib/utils';
import type { ScreeningCounts } from '@/types';

/**
 * The Guía I of the campaign, counted, and the way to who needs care (pantallas: Eval-Cerrada). Only for those who
 * channel people (num. 5.5).
 */
export default function TraumaSummary({
    url,
    counts,
    wide,
}: {
    url: Parameters<typeof screenings>[0];
    counts: ScreeningCounts;
    /** Alone in its row: title, text and button in one line on a wide screen. */
    wide: boolean;
}) {
    return (
        <section
            aria-labelledby="trauma-heading"
            className={cn(
                'flex flex-col gap-3.5 card p-6',
                wide && 'lg:flex-row lg:items-center lg:gap-6',
            )}
        >
            <div
                className={cn('flex items-center gap-3', wide && 'lg:shrink-0')}
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-risk-alto-bg text-risk-alto-fg">
                    <HandHeart aria-hidden className="size-5" />
                </span>
                <h2 id="trauma-heading" className="text-lg font-semibold">
                    Guía I · acontecimientos traumáticos
                </h2>
            </div>
            <p
                className={cn(
                    'text-sm text-muted-foreground',
                    wide && 'lg:flex-1',
                )}
            >
                {counts.requiring} de {counts.answered} personas que contestaron
                requieren valoración clínica. Solo las personas autorizadas ven
                quiénes son.
            </p>
            <Button
                asChild
                variant="outline"
                size="sm"
                className={cn('w-fit', wide && 'lg:shrink-0')}
            >
                <Link href={screenings(url)}>
                    <Lock aria-hidden="true" />
                    Ver canalización
                </Link>
            </Button>
        </section>
    );
}
