import { Head, usePage } from '@inertiajs/react';
import ReportDocument from '@/components/results/report-document';
import ReportShares from '@/components/results/report-shares';
import ReportToolbar from '@/components/results/report-toolbar';
import { Alert, AlertDescription } from '@/components/ui/alert';
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

    return (
        <>
            <Head title={`Informe · ${report.center.name}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8 print:p-0">
                <ReportToolbar
                    tenantSlug={tenantSlug}
                    campaignId={campaignId}
                    form={form}
                    durations={durations}
                />
                {!report.complete && (
                    <Alert className="print:hidden">
                        <AlertDescription>
                            Faltan las conclusiones, las recomendaciones y el
                            responsable de la evaluación.
                            {canEdit
                                ? ' Complétalos antes de entregar el informe.'
                                : ' Quien gestiona las evaluaciones puede completarlos.'}
                        </AlertDescription>
                    </Alert>
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
