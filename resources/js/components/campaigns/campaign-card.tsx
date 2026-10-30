import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import {
    campaignMeta,
    progressCaption,
    progressCount,
    progressNote,
} from '@/lib/campaigns';
import { show } from '@/routes/tenant/campaigns';
import type { CampaignSummary } from '@/types';

/**
 * An open campaign (design system: CampaignCard): how far it is, what state it is in, and the one thing to do next.
 */
export default function CampaignCard({
    tenantSlug,
    campaign,
}: {
    tenantSlug: string;
    campaign: CampaignSummary;
}) {
    const { progress } = campaign;

    return (
        <article className="flex flex-col gap-5 rounded-xl border bg-card p-6 shadow-xs">
            <header className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h3 className="text-lg leading-7 font-semibold">
                        {campaign.workCenter}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {campaign.kind === 'nom035_trauma' &&
                            `${campaign.kindLabel} · `}
                        {campaignMeta(campaign)}
                    </p>
                </div>
                <CampaignStatusBadge
                    status={campaign.status}
                    label={campaign.statusLabel}
                />
            </header>
            {progress === null ? (
                <p className="text-sm text-muted-foreground">
                    Revisa el borrador y lánzalo para invitar a las personas.
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {progressCount(progress)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {progressCaption(progress)}
                    </p>
                    <ProgressBar
                        value={progress.completed}
                        max={progress.target}
                        label={`Avance de ${campaign.workCenter}`}
                    />
                </div>
            )}
            <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p className="text-sm font-medium">
                    {progress === null ? 'Borrador' : progressNote(progress)}
                </p>
                <Button variant="outline" asChild>
                    <Link href={show([tenantSlug, campaign.id])}>
                        {campaign.status === 'draft'
                            ? 'Continuar borrador'
                            : 'Ver avance'}
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </footer>
        </article>
    );
}
