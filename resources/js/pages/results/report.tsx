import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import ReportDocument from '@/components/results/report-document';
import ReportShares from '@/components/results/report-shares';
import ReportToolbar from '@/components/results/report-toolbar';
import { resultsTrail } from '@/lib/results';
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
    canEdit,
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
    setLayoutProps({
        breadcrumbs: resultsTrail(
            tenantSlug,
            { id: campaignId, workCenter: report.center.name },
            'Informe 7.7',
        ),
    });

    return (
        <>
            <Head title={`Informe · ${report.center.name}`} />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16 print:max-w-none print:p-0">
                <ReportToolbar
                    tenantSlug={tenantSlug}
                    campaignId={campaignId}
                    form={form}
                    durations={durations}
                />
                {!report.complete && (
                    <div className="print:hidden">
                        <Notice>
                            <strong>
                                Faltan las conclusiones, las recomendaciones y
                                el responsable de la evaluación.
                            </strong>
                            {canEdit
                                ? ' Complétalos antes de entregar el informe.'
                                : ' Quien gestiona las evaluaciones puede completarlos.'}
                        </Notice>
                    </div>
                )}
                {shares !== null && (
                    <ReportShares
                        tenantSlug={tenantSlug}
                        campaignId={campaignId}
                        shares={shares}
                    />
                )}
                <ReportDocument report={report} levels={levels} />
            </div>
        </>
    );
}
