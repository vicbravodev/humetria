import ReportTable from '@/components/compensation/report-table';
import ScenarioSection from '@/components/compensation/scenario-section';
import { describeDate } from '@/lib/calendar-date';
import type { ScenarioReport } from '@/types';

/** The first year month by month, and the projection of the following years with the assumptions of the scenario. */
export default function ReportTimeline({ report }: { report: ScenarioReport }) {
    return (
        <>
            <ScenarioSection
                title="Mes a mes del primer año"
                description="Lo que paga la empresa cada mes desde la vigencia: la antigüedad sube sola y mueve vacaciones, prima y cuotas el mes de cada aniversario."
            >
                <ReportTable
                    caption="Costo de cada mes del primer año"
                    rows={report.months}
                    rowKey={(month) => month.month}
                    columns={[
                        {
                            label: 'Mes',
                            cell: (month) =>
                                describeDate(month.month) ?? month.month,
                        },
                        {
                            label: 'Antes',
                            cell: (month) => month.before_text,
                            right: true,
                        },
                        {
                            label: 'Después',
                            cell: (month) => month.after_text,
                            right: true,
                        },
                        {
                            label: 'Cambio',
                            cell: (month) => month.delta_text,
                            right: true,
                        },
                    ]}
                />
            </ScenarioSection>
            <ScenarioSection
                title={`Proyección a ${report.scenario.horizon_years} años`}
                description="Cada año con los supuestos de abajo: la antigüedad sube sola, las tasas patronales son las de ese año y la plantilla cambia con la rotación y el crecimiento."
            >
                <ReportTable
                    caption="Costo de cada año proyectado"
                    rows={report.projection}
                    rowKey={(year) => String(year.year)}
                    columns={[
                        {
                            label: 'Año',
                            cell: (year) =>
                                `Año ${year.year} · ${year.effective_on.slice(0, 4)}`,
                        },
                        {
                            label: 'Antes',
                            cell: (year) => year.before_text,
                            right: true,
                        },
                        {
                            label: 'Después',
                            cell: (year) => year.after_text,
                            right: true,
                        },
                        {
                            label: 'Cambio',
                            cell: (year) => year.delta_text,
                            right: true,
                        },
                        {
                            label: 'Retroactivo',
                            cell: (year) => year.retroactive_text ?? '—',
                            right: true,
                        },
                    ]}
                />
            </ScenarioSection>
        </>
    );
}
