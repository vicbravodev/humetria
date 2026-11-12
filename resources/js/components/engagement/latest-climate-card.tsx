import { Link } from '@inertiajs/react';
import ProtectedCell from '@/components/humetria/protected-cell';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { signedText } from '@/lib/engagement/trend';
import { results } from '@/routes/tenant/climate';
import type { ClimateLatest } from '@/types';

/**
 * «Del clima anterior»: the latest closed climate by its two indices, with the way to its results. As the protagonist
 * when nothing is in progress.
 */
export default function LatestClimateCard({
    latest,
    prominent = false,
}: {
    latest: ClimateLatest;
    prominent?: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="latest-climate"
            className="flex min-w-0 flex-col gap-4 card p-5 sm:p-6"
        >
            <div className="flex flex-col gap-1">
                <p className="text-sm text-muted-foreground">
                    {prominent ? 'Último resultado' : 'Del clima anterior'}
                </p>
                <h2 id="latest-climate" className="text-lg font-semibold">
                    {latest.name}
                </h2>
            </div>
            <dl className="flex flex-wrap gap-x-10 gap-y-3">
                <div className="flex flex-col">
                    <dt className="text-sm text-muted-foreground">
                        Compromiso
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {latest.index === null ? (
                            <ProtectedCell />
                        ) : (
                            `${latest.index} % favorable`
                        )}
                    </dd>
                </div>
                <div className="flex flex-col">
                    <dt className="text-sm text-muted-foreground">
                        Recomendación
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {latest.recommendation === null ? (
                            <ProtectedCell />
                        ) : (
                            signedText(latest.recommendation)
                        )}
                    </dd>
                </div>
            </dl>
            <Button
                asChild
                variant={prominent ? 'default' : 'outline'}
                className="self-start"
            >
                <Link href={results([tenantSlug, latest.id])}>
                    Ver resultados
                </Link>
            </Button>
        </section>
    );
}
