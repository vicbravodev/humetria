import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PrintButton from '@/components/results/print-button';
import ReportBar from '@/components/results/report-bar';
import ReportDetailsDialog from '@/components/results/report-details-dialog';
import ShareDialog from '@/components/results/share-dialog';
import { reportSections } from '@/lib/report';
import { results } from '@/routes/tenant/campaigns';
import type { ReportForm, ShareDurationOption } from '@/types';

/** The report's bar in the panel: back to the results, its parts, and editing (once complete), sharing and printing. */
export default function ReportToolbar({
    route,
    visible,
    complete,
    form,
    durations,
}: {
    route: { tenantSlug: string; campaignId: string };
    visible: boolean;
    complete: boolean;
    form: ReportForm | null;
    durations: ShareDurationOption[] | null;
}) {
    return (
        <ReportBar
            className="md:top-14"
            sections={reportSections(visible)}
            start={
                <Link
                    href={results([route.tenantSlug, route.campaignId])}
                    className="flex min-h-11 w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden="true" className="size-[18px]" />
                    Resultados
                </Link>
            }
        >
            {form !== null && complete && (
                <ReportDetailsDialog
                    tenantSlug={route.tenantSlug}
                    campaignId={route.campaignId}
                    form={form}
                    complete
                />
            )}
            {durations !== null && (
                <ShareDialog
                    tenantSlug={route.tenantSlug}
                    campaignId={route.campaignId}
                    durations={durations}
                />
            )}
            <PrintButton />
        </ReportBar>
    );
}
