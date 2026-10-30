import { Link } from '@inertiajs/react';
import { Calendar, ClipboardCheck, FileText, Users } from 'lucide-react';
import PageHeader from '@/components/page-header';
import type { PageFact } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { instrumentsText } from '@/lib/campaigns';
import { periodText } from '@/lib/results';
import { staffText } from '@/lib/work-centers';
import type { ResultsCampaign } from '@/types';

/**
 * The top of the results (pantallas: Eval-Cerrada): the work center with the state of its evaluation, its people,
 * guides and period, and the way to the report of num. 7.7 once there are results to report.
 */
export default function ResultsHeader({
    campaign,
    reportUrl,
}: {
    campaign: ResultsCampaign;
    /** Where the 7.7 report is; null while there is none to show. */
    reportUrl: string | null;
}) {
    const sample =
        campaign.sampleSize === null
            ? ''
            : ` (muestra de ${campaign.sampleSize})`;
    const period = periodText(campaign.launchedOn, campaign.closedOn);
    const facts: PageFact[] = [
        ...(campaign.workers === null
            ? []
            : [{ icon: Users, text: staffText(campaign.workers) }]),
        {
            icon: ClipboardCheck,
            text: `${instrumentsText(campaign.instruments)}${sample}`,
        },
        ...(period === null ? [] : [{ icon: Calendar, text: period }]),
    ];

    return (
        <PageHeader
            title={campaign.workCenter}
            status={
                <Badge variant={campaign.closed ? 'secondary' : 'default'}>
                    {campaign.closed ? 'Cerrada' : 'En curso'}
                </Badge>
            }
            facts={facts}
        >
            {reportUrl !== null && (
                <Button asChild variant="outline">
                    <Link href={reportUrl}>
                        <FileText aria-hidden="true" />
                        Informe 7.7
                    </Link>
                </Button>
            )}
        </PageHeader>
    );
}
