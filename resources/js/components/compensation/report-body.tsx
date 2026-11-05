import ReportAccuracy from '@/components/compensation/report-accuracy';
import ReportAnnex from '@/components/compensation/report-annex';
import ReportCost from '@/components/compensation/report-cost';
import ReportOthers from '@/components/compensation/report-others';
import ReportRaises from '@/components/compensation/report-raises';
import ReportSummary from '@/components/compensation/report-summary';
import ReportYears from '@/components/compensation/report-years';
import PrintButton from '@/components/humetria/print-button';
import SectionTabs from '@/components/humetria/section-tabs';
import { reportSections } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/**
 * The report of impact with its analysis (board V4-Comp-Reporte), inside the account or by a link: one notice of accuracy, the
 * tabs that stay on top, the summary and the parts. By a link (`shared`) it changes nothing and weighs against no other scenario.
 */
export default function ReportBody({
    report,
    union,
    shared,
    stickyClassName,
}: {
    report: ScenarioReport;
    union: boolean;
    shared: boolean;
    /** Where the tabs stick: under the top bar of the panel, or at the top of a page with none. */
    stickyClassName: string;
}) {
    const manages = !shared && report.can_annex;
    const withOthers = !shared && report.others.length > 0;
    const sections = reportSections(withOthers);
    const title = (id: string): string =>
        sections.find((section) => section.id === id)?.title ?? '';

    return (
        <>
            <ReportAccuracy report={report} canComplete={manages} />
            <SectionTabs
                label="Secciones del reporte"
                sections={sections}
                className={stickyClassName}
            >
                <PrintButton />
            </SectionTabs>
            <ReportSummary report={report} union={union} />
            <ReportCost report={report} title={title('costo')} />
            <ReportRaises
                report={report}
                title={title('quien')}
                union={union}
                canTry={manages}
            />
            <ReportYears
                report={report}
                title={title('anios')}
                canChange={manages}
            />
            {withOthers && (
                <ReportOthers report={report} title={title('frente')} />
            )}
            <ReportAnnex
                report={report}
                title={title('anexo')}
                personValue={union && !shared}
            />
            <p className="text-center text-caption text-muted-foreground">
                Datos agregados. Ningún monto identifica a una persona.
            </p>
        </>
    );
}
