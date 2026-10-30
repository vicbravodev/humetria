import { Head, usePage } from '@inertiajs/react';
import ReportDetailsDialog from '@/components/results/report-details-dialog';
import ReportDocument from '@/components/results/report-document';
import ReportPending from '@/components/results/report-pending';
import ReportShares from '@/components/results/report-shares';
import ReportToolbar from '@/components/results/report-toolbar';
import type {
    NomReport,
    Paginated,
    ReportForm,
    ReportShareRow,
    RiskLevelInfo,
    ShareDurationOption,
} from '@/types';

export default function Report({
    campaignId,
    report,
    levels,
    form,
    durations,
    shares,
}: {
    campaignId: string;
    report: NomReport;
    levels: RiskLevelInfo[];
    canEdit: boolean;
    form: ReportForm | null;
    durations: ShareDurationOption[] | null;
    shares: Paginated<ReportShareRow> | null;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={`Informe · ${report.center.name}`} />
            <ReportToolbar
                route={{ tenantSlug, campaignId }}
                visible={report.results.visible}
                complete={report.complete}
                form={form}
                durations={durations}
            />
            <div className="mx-auto flex w-full max-w-[1120px] flex-col gap-12 px-4 pt-6 pb-24 md:px-6 print:p-0">
                <ReportPending report={report}>
                    {form === null ? (
                        <span className="text-sm">
                            Quien gestiona las evaluaciones puede completarlos.
                        </span>
                    ) : (
                        <ReportDetailsDialog
                            tenantSlug={tenantSlug}
                            campaignId={campaignId}
                            form={form}
                            complete={false}
                        />
                    )}
                </ReportPending>
                <ReportDocument report={report} levels={levels} />
                {shares !== null && (
                    <ReportShares
                        tenantSlug={tenantSlug}
                        campaignId={campaignId}
                        shares={shares}
                    />
                )}
            </div>
        </>
    );
}
