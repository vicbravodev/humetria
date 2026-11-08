import { Head, setLayoutProps } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import ReportFilters from '@/components/attendance/report-filters';
import ReportLegend from '@/components/attendance/report-legend';
import ReportPeople from '@/components/attendance/report-people';
import ReportStart from '@/components/attendance/report-start';
import ReportSummary from '@/components/attendance/report-summary';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { weekTitle } from '@/lib/attendance/week-text';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceReportProps } from '@/types';

/**
 * The report for the authority (LFT art. 132-XXXIV; pantallas Empleados and Eval-Cerrada: header with the download, the
 * period's figures, the people page by page): every day of every person in one center and a period, to hand over.
 */
export default function AttendanceReport(props: AttendanceReportProps) {
    const { center, period, summary, people, exportUrl } = props;
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: 'Reporte para la autoridad' },
        ],
    });

    return (
        <>
            <Head title="Reporte para la autoridad · Asistencia" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={center?.name ?? 'Registro de jornada'}
                    title="Reporte para la autoridad"
                    description={
                        center === null
                            ? 'Cada día de cada persona en un centro y un periodo, listo para entregar cuando la autoridad laboral lo pida.'
                            : weekTitle(period.from, period.to)
                    }
                >
                    {exportUrl !== null && (
                        <Button asChild>
                            <a href={exportUrl} download>
                                <FileDown aria-hidden />
                                Descargar CSV
                            </a>
                        </Button>
                    )}
                </PageHeader>
                <ReportFilters center={center} period={period} />
                {summary === null || people === null || center === null ? (
                    <ReportStart />
                ) : (
                    <>
                        <ReportSummary summary={summary} />
                        <ReportPeople
                            people={people}
                            filters={{
                                ...period,
                                center: center.id,
                                q: props.filters.q,
                            }}
                        />
                    </>
                )}
                <ReportLegend generatedAt={props.generatedAt} />
            </div>
        </>
    );
}
