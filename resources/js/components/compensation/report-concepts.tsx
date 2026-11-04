import ReportTable from '@/components/compensation/report-table';
import ScenarioSection from '@/components/compensation/scenario-section';
import type { ReportConcept } from '@/types';

/** The cascade of the first year: the salary, each benefit and each employer line, before and after the proposal. */
export default function ReportConcepts({
    concepts,
}: {
    concepts: ReportConcept[];
}) {
    return (
        <ScenarioSection
            title="Cascada por concepto"
            description="Lo que arrastra el aumento: cada prestación y cada cuota patronal, a doce meses de la vigencia. El cambio de cada concepto incluye el retroactivo en la última columna."
        >
            <ReportTable
                caption="Costo del primer año por concepto"
                rows={concepts}
                rowKey={(concept) => `${concept.type}:${concept.key}`}
                columns={[
                    { label: 'Concepto', cell: (concept) => concept.label },
                    {
                        label: 'Antes',
                        cell: (concept) => concept.before_text,
                        right: true,
                    },
                    {
                        label: 'Después',
                        cell: (concept) => concept.after_text,
                        right: true,
                    },
                    {
                        label: 'Cambio',
                        cell: (concept) => concept.delta_text,
                        right: true,
                    },
                    {
                        label: 'Cambio con retroactivo',
                        cell: (concept) => concept.first_year_text,
                        right: true,
                    },
                ]}
            />
        </ScenarioSection>
    );
}
