import RiskLevel from '@/components/humetria/risk-level';
import AnnexRow from '@/components/results/annex-row';
import RiskScale from '@/components/results/risk-scale';
import ReportSection from '@/components/results/report-section';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { nestDomains } from '@/lib/report';
import type { NomReport, RiskLevelInfo } from '@/types';

/** Anexo A, every group counted by level (when there are results to show), and Anexo B, what each level needs. */
export default function ReportAnnex({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    const { results } = report;

    return (
        <ReportSection
            id="anexo"
            title={
                results.visible
                    ? 'Anexo A. Cuestionarios por nivel de riesgo'
                    : 'Anexo. Necesidad de acción por nivel'
            }
            description={
                results.visible
                    ? `Número de cuestionarios en cada nivel y su porcentaje sobre ${results.answered}. Sin promedios.`
                    : 'Criterios para la toma de acciones, tal como los establece la norma.'
            }
            pageBreak
        >
            {results.visible && (
                <>
                    <div className="rounded-lg border bg-card">
                        <Table className="min-w-[760px]">
                            <TableCaption className="sr-only">
                                Cuestionarios por nivel de riesgo
                            </TableCaption>
                            <TableHeader>
                                <TableRow className="bg-muted hover:bg-muted">
                                    <TableHead className="w-[30%] min-w-64 pl-5">
                                        Categoría / dominio
                                    </TableHead>
                                    {levels.map((level) => (
                                        <TableHead
                                            key={level.value}
                                            className="text-right"
                                        >
                                            <RiskLevel
                                                level={level.value}
                                                label={level.label}
                                            />
                                        </TableHead>
                                    ))}
                                    <TableHead className="pr-5 text-right">
                                        Alto o muy alto
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <AnnexRow
                                    label="Calificación final"
                                    counts={results.final}
                                    kind="final"
                                />
                                {nestDomains(
                                    results.categories,
                                    results.domains,
                                ).map(({ category, domains }) => [
                                    <AnnexRow
                                        key={category.key}
                                        label={category.label}
                                        counts={category.levels}
                                        kind="category"
                                    />,
                                    ...domains.map((domain) => (
                                        <AnnexRow
                                            key={domain.key}
                                            label={domain.label}
                                            counts={domain.levels}
                                            kind="domain"
                                        />
                                    )),
                                ])}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="mt-6 flex flex-col gap-1.5">
                        <h2 className="text-[28px] leading-9 font-semibold tracking-[-0.01em]">
                            Anexo B. Necesidad de acción por nivel
                        </h2>
                        <p className="max-w-[760px] text-muted-foreground">
                            Criterios para la toma de acciones, tal como los
                            establece la{' '}
                            {report.guide.replace('Guía', 'Guía de referencia')}
                            .
                        </p>
                    </div>
                </>
            )}
            <RiskScale levels={levels} />
        </ReportSection>
    );
}
