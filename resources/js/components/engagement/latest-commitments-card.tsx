import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { index as commitments } from '@/routes/tenant/climate/commitments';
import type { ClimateLatest } from '@/types';

/** «Compromisos» of the latest climate: how many, by state, and the way to them. The cycle does not end at the results. */
export default function LatestCommitmentsCard({
    latest,
}: {
    latest: ClimateLatest;
}) {
    const tenantSlug = useTenantSlug();
    const { open, in_progress: doing, done } = latest.commitments;
    const total = open + doing + done;

    return (
        <section
            aria-labelledby="latest-commitments"
            className="flex min-w-0 flex-col gap-4 card p-5 sm:p-6"
        >
            <div className="flex flex-col gap-1">
                <p className="text-sm text-muted-foreground">Compromisos</p>
                <h2 id="latest-commitments" className="text-lg font-semibold">
                    {total === 0
                        ? 'Todavía no hay compromisos'
                        : countOf(total, 'compromiso', 'compromisos')}
                </h2>
            </div>
            <p className="text-muted-foreground">
                {total === 0
                    ? 'Convierte lo que dijo la gente en compromisos con responsable y fecha.'
                    : `${open} por hacer · ${doing} en marcha · ${done} ${done === 1 ? 'cumplido' : 'cumplidos'}`}
            </p>
            <Button asChild variant="outline" className="self-start">
                <Link href={commitments([tenantSlug, latest.id])}>
                    {total === 0 ? 'Escribir compromisos' : 'Ver compromisos'}
                </Link>
            </Button>
        </section>
    );
}
