import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { share } from '@/routes/tenant/disclosure/results';
import type { EvidenceCenter } from '@/types';

const STATES: Record<EvidenceCenter['results']['state'], string> = {
    ready: 'Lista para publicar',
    done: 'Publicados',
    waiting: 'Sin evaluación cerrada',
    not_applicable: 'No aplica: hasta 15 personas',
};

/**
 * «Resultados para las personas» (board V4-Difusion-Registro, num. 7.8): by center, the evaluation ready to publish first, with
 * what the people will see and its way to the summary to publish (criterio C-54); then where the rest stand. Never a figure here.
 */
export default function PeopleResultsCard({
    centers,
}: {
    centers: EvidenceCenter[];
}) {
    const tenantSlug = useTenantSlug();
    const ready = centers.filter(({ results }) => results.state === 'ready');
    const rest = centers.filter(({ results }) => results.state !== 'ready');

    return (
        <section
            aria-labelledby="people-results"
            className="flex flex-col gap-4 card p-5 md:p-7"
        >
            <div className="flex flex-col gap-1">
                <h2
                    id="people-results"
                    className="text-xl leading-7 font-semibold"
                >
                    Resultados para las personas
                </h2>
                <p className="text-sm text-muted-foreground">
                    Tienen derecho a conocerlos. Tú decides cuándo se publican.
                </p>
            </div>
            {ready.map((center) => (
                <div
                    key={center.id}
                    className="flex flex-col gap-2.5 rounded-xl bg-accent px-4 py-4"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="flex flex-col">
                            <span className="font-semibold">{center.name}</span>
                            <span className="text-sm text-muted-foreground">
                                Cerrada el {center.results.closedOn}
                            </span>
                        </p>
                        {center.results.campaignId !== null && (
                            <Button asChild size="sm">
                                <Link
                                    href={share.url([
                                        tenantSlug,
                                        center.results.campaignId,
                                    ])}
                                >
                                    Publicar
                                </Link>
                            </Button>
                        )}
                    </div>
                    <p className="text-sm">
                        Verán cuántas personas contestaron y los temas que más
                        atención necesitan. Nunca datos de áreas o grupos con
                        menos de 5 personas.
                    </p>
                </div>
            ))}
            <ul className="flex flex-col gap-2.5">
                {rest.map((center) => (
                    <li
                        key={center.id}
                        className="flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm"
                    >
                        <span>{center.name}</span>
                        {center.results.state === 'done' &&
                        center.results.campaignId !== null ? (
                            <Link
                                href={share.url([
                                    tenantSlug,
                                    center.results.campaignId,
                                ])}
                                className="touch-hit font-medium text-primary"
                            >
                                Publicados · ver lo que ven
                            </Link>
                        ) : (
                            <span className="text-muted-foreground">
                                {STATES[center.results.state]}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
