import { Link } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { comparesAll, todaySentence } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import { compare, index } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioCounts } from '@/types';

/** The heading of the scenarios on the start: what today costs, the archived ones a link away and «Comparar». */
export default function ScenarioStartHeading({
    ids,
    counts,
    today,
}: {
    /** The scenarios on this page. */
    ids: string[];
    counts: ScenarioCounts;
    today: { people: number; cost_text: string } | null;
}) {
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();
    const all = comparesAll(counts.active, ids.length);

    return (
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
            <div className="grid gap-1">
                <h2
                    id="start-scenarios"
                    className="text-xl leading-7 font-semibold"
                >
                    {union ? 'Tus propuestas' : 'Tus escenarios'}
                </h2>
                {today !== null && (
                    <p className="text-sm text-muted-foreground">
                        {todaySentence(today, union)}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                {counts.archived > 0 && (
                    <Link
                        href={index(tenantSlug, {
                            query: { status: 'archived' },
                        })}
                        className="touch-hit text-sm font-medium text-primary hover:underline"
                    >
                        {countOf(counts.archived, 'archivado', 'archivados')}
                    </Link>
                )}
                {counts.active >= 2 && (
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                all
                                    ? compare(tenantSlug, { query: { ids } })
                                    : index(tenantSlug)
                            }
                        >
                            <Scale />
                            {all
                                ? `Comparar los ${counts.active}`
                                : 'Elegir cuáles comparar'}
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
