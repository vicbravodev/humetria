import { Link } from '@inertiajs/react';
import ArcMeter from '@/components/humetria/arc-meter';
import { Button } from '@/components/ui/button';
import { openCampaignLine } from '@/lib/evaluation-centers';
import { show } from '@/routes/tenant/campaigns';
import type { CampaignSummary } from '@/types';

/** A running evaluation on its work center's card (pantalla NOM035): the arch, who answered, when it closes. */
export default function CenterProgress({
    tenantSlug,
    campaign,
}: {
    tenantSlug: string;
    campaign: CampaignSummary;
}) {
    const completed = campaign.progress?.completed ?? 0;
    const target = campaign.progress?.target ?? 0;
    const answered = `${completed} de ${target}`;

    return (
        <div className="flex flex-wrap items-center gap-4">
            <ArcMeter
                value={completed}
                max={target}
                label={`${answered} contestaron`}
                size="sm"
            />
            <div className="flex min-w-0 grow flex-col gap-0.5">
                <p className="text-sm">
                    <span className="font-semibold tabular-nums">
                        {answered}
                    </span>{' '}
                    contestaron
                </p>
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    {openCampaignLine(campaign)}
                </p>
            </div>
            <Button size="sm" asChild>
                <Link href={show([tenantSlug, campaign.id])}>
                    Ver avance
                    <span className="sr-only"> de {campaign.workCenter}</span>
                </Link>
            </Button>
        </div>
    );
}
