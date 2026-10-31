import CenterFollowUp from '@/components/campaigns/center-follow-up';
import CenterProgress from '@/components/campaigns/center-progress';
import { Badge } from '@/components/ui/badge';
import {
    centerSize,
    centerStatus,
    numeralsText,
} from '@/lib/evaluation-centers';
import { staffText } from '@/lib/work-centers';
import type { WorkCenterEvaluation } from '@/types';

/**
 * A work center to evaluate (pantalla NOM035): where it stands, its people and the guides they call for, then its
 * running evaluation or its last one.
 */
export default function WorkCenterCard({
    tenantSlug,
    center,
    canManage,
    minGroup,
}: {
    tenantSlug: string;
    center: WorkCenterEvaluation;
    canManage: boolean;
    minGroup: number;
}) {
    const status = centerStatus(center);
    const facts = [
        ['Personal', staffText(center.workers)],
        ['Guías que aplican', numeralsText(center.guides)],
        ['Por qué', centerSize(center.guides)?.range ?? '—'],
    ];

    return (
        <article className="flex flex-col gap-[18px] rounded-xl border bg-card p-[22px]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h3 className="text-lg leading-[26px] font-semibold">
                        {center.name}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {center.activity}
                    </p>
                </div>
                <Badge variant={status.variant}>{status.label}</Badge>
            </div>
            <dl className="grid grid-cols-3 gap-3 border-b pb-[18px]">
                {facts.map(([term, value]) => (
                    <div key={term} className="flex flex-col gap-0.5">
                        <dt className="text-[13px] leading-[18px] text-muted-foreground">
                            {term}
                        </dt>
                        <dd className="font-medium tabular-nums">{value}</dd>
                    </div>
                ))}
            </dl>
            {center.openCampaign?.status === 'active' ? (
                <CenterProgress
                    tenantSlug={tenantSlug}
                    campaign={center.openCampaign}
                />
            ) : (
                <CenterFollowUp
                    tenantSlug={tenantSlug}
                    center={center}
                    canManage={canManage}
                    minGroup={minGroup}
                />
            )}
        </article>
    );
}
