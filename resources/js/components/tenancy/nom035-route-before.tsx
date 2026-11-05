import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Urgency } from '@/types';

/**
 * «Antes de seguir» (board V2-Ruta-N035): the people the Guía I says need a clinical assessment and nobody channeled yet,
 * only for whoever may see it. Counts only, never who.
 */
export default function Nom035RouteBefore({
    urgencies,
}: {
    urgencies: Urgency[];
}) {
    return (
        <section
            aria-labelledby="before"
            className="flex flex-col gap-3 rounded-2xl bg-risk-alto-bg p-[22px] text-risk-alto-fg"
        >
            <h2 id="before" className="text-sm font-semibold">
                Antes de seguir
            </h2>
            {urgencies.map((urgency) => (
                <div key={urgency.href} className="flex flex-col gap-3">
                    <p className="text-foreground">
                        {urgency.title}. Es lo más sensible de toda la ruta.
                    </p>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="self-start"
                    >
                        <Link href={urgency.href}>Canalizar ahora</Link>
                    </Button>
                </div>
            ))}
        </section>
    );
}
