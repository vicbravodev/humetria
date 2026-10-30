import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PrintButton from '@/components/results/print-button';
import ReportDetailsDialog from '@/components/results/report-details-dialog';
import ReportDocument from '@/components/results/report-document';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { results } from '@/routes/tenant/campaigns';
import type { NomReport, ReportForm, RiskLevelInfo } from '@/types';

export default function Report({
    campaignId,
    report,
    levels,
    canEdit,
    form,
}: {
    campaignId: string;
    report: NomReport;
    levels: RiskLevelInfo[];
    canEdit: boolean;
    form: ReportForm | null;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={`Informe · ${report.center.name}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8 print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-4 print:hidden">
                    <Link
                        href={results([tenantSlug, campaignId])}
                        className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        Resultados
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && form !== null && (
                            <ReportDetailsDialog
                                tenantSlug={tenantSlug}
                                campaignId={campaignId}
                                form={form}
                            />
                        )}
                        <PrintButton />
                    </div>
                </div>
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
                <ReportDocument report={report} levels={levels} />
            </div>
        </>
    );
}
