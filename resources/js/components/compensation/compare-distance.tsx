import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/compensation/scenarios';
import type {
    CompareDistance as Distance,
    CompareMidpoint,
    CompareMoves,
} from '@/types';

/**
 * «La distancia a negociar» (board V4-Comp-Comparar): how far apart the two dearest proposals are, the first year and each
 * year after, and how the cost moves with a point and a month of retroactivity, with «Probar un punto medio».
 */
export default function CompareDistance({
    distance,
    moves,
    midpoint,
}: {
    distance: Distance;
    moves: CompareMoves;
    midpoint: CompareMidpoint | null;
}) {
    const tenantSlug = useTenantSlug();
    const lines = [
        ['1 punto a sindicalizados', `≈ ${moves.unionized_point_text} al año`],
        ...(moves.trusted_point_text === null
            ? []
            : [
                  [
                      '1 punto a confianza',
                      `≈ ${moves.trusted_point_text} al año`,
                  ],
              ]),
        ['1 mes de retroactivo', `≈ ${moves.retroactive_month_text}`],
    ];

    return (
        <section
            aria-labelledby="compare-distance"
            className="grid gap-6 card border-l-4 border-l-primary p-6 shadow-raised lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center"
        >
            <div className="grid gap-2">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    La distancia a negociar
                </p>
                <h2
                    id="compare-distance"
                    className="text-xl leading-7 font-semibold text-balance"
                >
                    Entre «{distance.from}» y «{distance.to}» hay{' '}
                    {distance.first_year_text} el primer año
                </h2>
                <p className="text-muted-foreground">
                    Y {distance.recurring_text} al año, al arranque.
                    {distance.mostly === null
                        ? ''
                        : ` La mayor parte viene de ${distance.mostly}.`}
                </p>
            </div>
            <div className="grid gap-3 rounded-xl bg-muted px-5 py-4">
                <h3 className="font-semibold">Cómo se mueve</h3>
                <dl className="grid gap-2 text-sm">
                    {lines.map(([label, value]) => (
                        <div key={label} className="flex justify-between gap-4">
                            <dt>{label}</dt>
                            <dd className="font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
                {midpoint !== null && (
                    <Button
                        variant="outline"
                        asChild
                        className="justify-self-start"
                    >
                        <Link
                            href={create(tenantSlug, {
                                query: { ...midpoint, name: 'Punto medio' },
                            })}
                        >
                            Probar un punto medio
                        </Link>
                    </Button>
                )}
            </div>
        </section>
    );
}
