import ReportAnnex from '@/components/compensation/report-annex';
import ReportCaveats from '@/components/compensation/report-caveats';
import ReportCost from '@/components/compensation/report-cost';
import ReportEvaluate from '@/components/compensation/report-evaluate';
import ReportRaises from '@/components/compensation/report-raises';
import ReportScenarios from '@/components/compensation/report-scenarios';
import ReportSteps from '@/components/compensation/report-steps';
import ReportVerdict from '@/components/compensation/report-verdict';
import ReportYears from '@/components/compensation/report-years';
import PrintButton from '@/components/humetria/print-button';
import SectionTabs from '@/components/humetria/section-tabs';
import { reportSections } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/**
 * The report of impact (board V4-Comp-Reporte), inside the account or by a link, in the order a company decides in: the answer,
 * where the cost comes from, the calculation step by step, who receives it, what it is weighed against, the next years, what else
 * to evaluate, the assumptions and the annex. By a link (`shared`) it changes nothing and weighs against no other scenario.
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
    const manages = !shared && report.can_manage;
    const others = !shared && report.others.length > 0;
    const ladder = report.ladder.length > 0;
    // A payroll of one kind of worker has one row to say who receives the raise: the steps already say it.
    const quien = report.scenario.source !== 'average';
    const sections = reportSections({ quien, ladder, others });
    const title = (id: string): string =>
        sections.find((section) => section.id === id)?.title ?? '';

    return (
        <>
            <SectionTabs
                label="Secciones del reporte"
                sections={sections}
                className={stickyClassName}
            >
                {shared ? undefined : <PrintButton />}
            </SectionTabs>
            <ReportVerdict report={report} union={union} />
            <ReportCost report={report} title={title('costo')} />
            <ReportSteps report={report} title={title('calculo')} />
            {quien && (
                <ReportRaises
                    report={report}
                    title={title('quien')}
                    union={union}
                    canTry={manages}
                />
            )}
            {(ladder || others) && (
                <ReportScenarios report={report} title={title('frente')} />
            )}
            <ReportYears
                report={report}
                title={title('anios')}
                canChange={manages}
            />
            <ReportEvaluate title={title('evaluar')} />
            <ReportCaveats
                report={report}
                title={title('supuestos')}
                canFix={manages}
            />
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
