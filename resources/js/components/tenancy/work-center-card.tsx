import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import WorkCenterAreas from '@/components/tenancy/work-center-areas';
import WorkCenterFacts from '@/components/tenancy/work-center-facts';
import WorkCenterRowActions from '@/components/tenancy/work-center-row-actions';
import { Badge } from '@/components/ui/badge';
import { evaluationStanding } from '@/lib/work-centers';
import { index as employees } from '@/routes/tenant/employees';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

/**
 * One work center (pantallas: Centros): what it does and where, its people and the guides that apply, its largest
 * areas and where it stands with its evaluations.
 */
export default function WorkCenterCard({
    tenantSlug,
    workCenter,
    states,
}: {
    tenantSlug: string;
    workCenter: WorkCenterRow;
    /** The states the edit form offers; null when the person may not change work centers, nor see its people. */
    states: MexicanStateOption[] | null;
}) {
    const standing = evaluationStanding(
        workCenter.evaluation,
        workCenter.employees,
    );

    return (
        <article className="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-[22px] transition-colors duration-150 ease-out hover:border-input">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2 className="text-lg leading-[26px] font-semibold">
                        {workCenter.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {workCenter.main_activity}
                    </p>
                </div>
                {states !== null && (
                    <WorkCenterRowActions
                        tenantSlug={tenantSlug}
                        workCenter={workCenter}
                        states={states}
                    />
                )}
            </div>
            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                <MapPin aria-hidden className="mt-0.5 size-4 shrink-0" />
                {workCenter.address}
            </p>
            <WorkCenterFacts workCenter={workCenter} next={standing.next} />
            <WorkCenterAreas workCenter={workCenter} />
            <div className="mt-auto flex flex-wrap items-center justify-between gap-2">
                {workCenter.archived_on === null ? (
                    <Badge variant={standing.variant}>{standing.badge}</Badge>
                ) : (
                    <Badge variant="secondary">
                        Archivado el {workCenter.archived_on}
                    </Badge>
                )}
                {states !== null && workCenter.employees > 0 && (
                    <Link
                        href={employees(tenantSlug, {
                            query: { work_center: workCenter.id },
                        })}
                        aria-label={`Ver personas de ${workCenter.name}`}
                        className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        Ver personas
                    </Link>
                )}
            </div>
        </article>
    );
}
