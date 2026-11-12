import GroupMapLegend from '@/components/engagement/results/group-map-legend';
import GroupMapTable from '@/components/engagement/results/group-map-table';
import CountTabs from '@/components/humetria/count-tabs';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { mapColumns } from '@/lib/engagement/results';
import type { EngagementResults } from '@/types';

/**
 * «Por grupo» as a map of groups × the five themes to act on first (pantalla Clima · Resultados). One attribute at a
 * time, chosen in the tabs; every figure comes from the server under the minimum of 5 applied after cutting.
 */
export default function GroupMap({ results }: { results: EngagementResults }) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const at: [string, string] = [tenantSlug, results.survey.id];

    return (
        <section
            id="por-grupo"
            aria-labelledby="by-group"
            className="flex flex-col gap-4"
        >
            <div className="flex flex-col gap-1">
                <h2 id="by-group" className="text-xl font-semibold">
                    Por grupo
                </h2>
                <p className="text-sm text-muted-foreground">
                    Respuestas favorables por tema. Elige cómo agrupar.
                </p>
            </div>
            <CountTabs
                label="Agrupar por"
                tabs={results.cuts.map((cut) => ({
                    label: cut.label,
                    href: routes.results.url(at, {
                        query: { cut: cut.value },
                    }),
                    active: results.cut === cut.value,
                }))}
            />
            {results.groups === null ? (
                <p className="text-muted-foreground">
                    Elige cómo agrupar para ver cada grupo junto a toda la
                    empresa.
                </p>
            ) : (
                <>
                    <GroupMapTable
                        columns={mapColumns(
                            results.drivers,
                            results.priorities,
                        )}
                        groups={results.groups}
                        hasHidden={results.hasHidden}
                    />
                    <GroupMapLegend hasHidden={results.hasHidden} />
                </>
            )}
        </section>
    );
}
