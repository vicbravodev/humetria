import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PrintButton from '@/components/results/print-button';
import ReportDetailsDialog from '@/components/results/report-details-dialog';
import ShareDialog from '@/components/results/share-dialog';
import { results } from '@/routes/tenant/campaigns';
import type { ReportForm, ShareDurationOption } from '@/types';

/** Back to the results, and what can be done with the report: fill it in, share it, print it. */
export default function ReportToolbar({
    tenantSlug,
    campaignId,
    form,
    durations,
}: {
    tenantSlug: string;
    campaignId: string;
    form: ReportForm | null;
    durations: ShareDurationOption[] | null;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 print:hidden">
            <Link
                href={results([tenantSlug, campaignId])}
                className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft aria-hidden="true" className="size-4" />
                Resultados
            </Link>
            <div className="flex flex-wrap gap-2">
                {form !== null && (
                    <ReportDetailsDialog
                        tenantSlug={tenantSlug}
                        campaignId={campaignId}
                        form={form}
                    />
                )}
                {durations !== null && (
                    <ShareDialog
                        tenantSlug={tenantSlug}
                        campaignId={campaignId}
                        durations={durations}
                    />
                )}
                <PrintButton />
            </div>
        </div>
    );
}
