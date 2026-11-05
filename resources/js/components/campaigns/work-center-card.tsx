import CenterFollowUp from '@/components/campaigns/center-follow-up';
import { Link } from '@inertiajs/react';
import CenterProgress from '@/components/campaigns/center-progress';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    centerSize,
    centerStatus,
    numeralsText,
} from '@/lib/evaluation-centers';
import { staffText } from '@/lib/work-centers';
import { route } from '@/routes/tenant/nom035';
import type { WorkCenterEvaluation } from '@/types';

/**
 * A work center to evaluate (pantalla NOM035): where it stands, its people and the guides they call for, the way to its
 * route (board V2-Ruta-N035), then its running evaluation or its last one.
 */
export default function WorkCenterCard({
    center,
    canManage,
    minGroup,
}: {
    center: WorkCenterEvaluation;
    canManage: boolean;
    minGroup: number;
}) {
    const tenantSlug = useTenantSlug();
    const status = centerStatus(center);
    const facts = [
        ['Personal', staffText(center.workers)],
        ['Guías que aplican', numeralsText(center.guides)],
        ['Por qué', centerSize(center.guides)?.range ?? '—'],
    ];

    return (
        <article className="flex flex-col gap-[18px] card p-[22px]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h3 className="text-lg leading-[26px] font-semibold">
                        {center.name}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {center.activity}
                    </p>
                    <Link
                        href={route([tenantSlug, center.id])}
                        className="touch-hit self-start text-sm font-medium text-primary hover:underline"
                    >
                        Ver ruta
                        <span className="sr-only"> de {center.name}</span>
                    </Link>
                </div>
                <Badge variant={status.variant}>{status.label}</Badge>
            </div>
            <dl className="grid grid-cols-3 gap-3 border-b pb-[18px]">
                {facts.map(([term, value]) => (
                    <div key={term} className="flex flex-col gap-0.5">
                        <dt className="text-caption text-muted-foreground">
                            {term}
                        </dt>
                        <dd className="font-medium tabular-nums">{value}</dd>
                    </div>
                ))}
            </dl>
            {center.openCampaign?.status === 'active' ? (
                <CenterProgress campaign={center.openCampaign} />
            ) : (
                <CenterFollowUp
                    center={center}
                    canManage={canManage}
                    minGroup={minGroup}
                />
            )}
        </article>
    );
}
