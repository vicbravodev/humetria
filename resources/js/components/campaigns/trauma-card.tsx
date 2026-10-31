import { Link } from '@inertiajs/react';
import { HeartHandshake, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { screenings } from '@/routes/tenant/campaigns';

/**
 * Guía I of the evaluation (num. 5.5): who requires a clinical assessment lives in the channeling list, which only
 * those allowed to see Guía I open, and each opening is recorded.
 */
export default function TraumaCard({ route }: { route: [string, string] }) {
    return (
        <section
            aria-labelledby="trauma-title"
            className="flex flex-col gap-3.5 rounded-xl border bg-card p-6"
        >
            <div className="flex items-center gap-3">
                <span className="inline-flex size-9 items-center justify-center rounded-[10px] bg-risk-alto-bg text-risk-alto-fg">
                    <HeartHandshake
                        aria-hidden="true"
                        className="size-[18px]"
                    />
                </span>
                <h2
                    id="trauma-title"
                    className="text-lg leading-7 font-semibold"
                >
                    Guía I · acontecimientos traumáticos
                </h2>
            </div>
            <p className="text-sm text-muted-foreground">
                Quienes requieren valoración clínica están en la canalización.
                Solo las personas autorizadas ven quiénes son.
            </p>
            <Button asChild variant="outline" size="sm" className="w-fit">
                <Link href={screenings(route)}>
                    <Lock aria-hidden="true" />
                    Ver canalización
                </Link>
            </Button>
        </section>
    );
}
