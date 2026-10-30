import PageHeader from '@/components/page-header';
import PrintButton from '@/components/results/print-button';
import ReportDetailsDialog from '@/components/results/report-details-dialog';
import ShareDialog from '@/components/results/share-dialog';
import type { ReportForm, ShareDurationOption } from '@/types';

/** The top of the report page, never printed: what it is, and what can be done with it: fill it in, share it, print it. */
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
        <div className="print:hidden">
            <PageHeader
                title="Informe 7.7"
                description="El informe del numeral 7.7, tal como se imprime. Complétalo, compártelo con un enlace temporal o guárdalo en PDF."
            >
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
            </PageHeader>
        </div>
    );
}
