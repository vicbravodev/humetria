import { ChevronDown } from 'lucide-react';
import ReportAssumptions from '@/components/compensation/report-assumptions';
import ReportConceptTable from '@/components/compensation/report-concept-table';
import ReportCoverage from '@/components/compensation/report-coverage';
import ReportCuts from '@/components/compensation/report-cuts';
import ReportLiability from '@/components/compensation/report-liability';
import ReportPart from '@/components/compensation/report-part';
import ReportPersonValue from '@/components/compensation/report-person-value';
import ReportTimeline from '@/components/compensation/report-timeline';
import { annexNote } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/** «Anexo: detalle por concepto», and folded below it the rest of the detail: month by month, the cuts, the liability and the assumptions. */
export default function ReportAnnex({
    report,
    title,
    personValue,
}: {
    report: ScenarioReport;
    title: string;
    /** The worker's gross against the company's cost: only inside a union's account. */
    personValue: boolean;
}) {
    return (
        <ReportPart
            id="anexo"
            title={title}
            description="Antes y después, a 12 meses de vigencia. El cambio con retroactivo es lo que suma al primer año."
            pageBreak
        >
            <div className="overflow-hidden card">
                <ReportConceptTable report={report} />
            </div>
            <p className="text-caption text-muted-foreground">
                {annexNote(report)}
            </p>
            <details className="group card print:hidden">
                <summary
                    data-test="report-detail"
                    className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 font-medium [&::-webkit-details-marker]:hidden"
                >
                    Más detalle: mes a mes, dónde pesa, prima de antigüedad y
                    supuestos
                    <ChevronDown
                        aria-hidden
                        className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-180"
                    />
                </summary>
                <div className="flex flex-col gap-4 border-t p-4 sm:p-5">
                    <ReportCoverage report={report} />
                    {personValue && (
                        <ReportPersonValue value={report.person_value} />
                    )}
                    <ReportTimeline report={report} />
                    <ReportCuts cuts={report.cuts} />
                    <ReportLiability report={report} />
                    <ReportAssumptions assumptions={report.assumptions} />
                </div>
            </details>
        </ReportPart>
    );
}
