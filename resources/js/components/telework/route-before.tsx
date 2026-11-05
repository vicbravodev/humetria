import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { accidentsLine } from '@/lib/telework/route';
import type { RouteAccidents } from '@/types';

/** «Antes de seguir» (board V2-Ruta-N037): the accidents nobody answered, before any stage. */
export default function RouteBefore({
    accidents,
}: {
    accidents: RouteAccidents[];
}) {
    return accidents.map((each) => (
        <section
            key={each.href}
            aria-labelledby="before"
            className="flex flex-col gap-3 rounded-2xl bg-risk-alto-bg p-[22px] text-risk-alto-fg"
        >
            <h2 id="before" className="text-sm font-semibold">
                Antes de seguir
            </h2>
            <p className="text-foreground">{accidentsLine(each)}</p>
            <Button asChild variant="outline" size="sm" className="self-start">
                <Link href={each.href}>
                    {each.count === 1
                        ? 'Revisar el aviso'
                        : 'Revisar los avisos'}
                </Link>
            </Button>
        </section>
    ));
}
