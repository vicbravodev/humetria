import ReportTable from '@/components/compensation/report-table';
import ScenarioSection from '@/components/compensation/scenario-section';
import { describeDate } from '@/lib/calendar-date';
import type { ScenarioReport } from '@/types';

/** Every assumption of the long term with its value, where it comes from and when it was set, and what the report is not. */
export default function ReportAssumptions({
    assumptions,
}: {
    assumptions: ScenarioReport['assumptions'];
}) {
    return (
        <ScenarioSection
            title="Supuestos y alcance"
            description="Ninguna cifra va sin sus supuestos. Este reporte no sustituye una valuación actuarial (NIF D-3) ni es asesoría legal o fiscal."
        >
            <ReportTable
                caption="Supuestos del largo plazo"
                rows={assumptions}
                rowKey={(assumption) => assumption.label}
                columns={[
                    {
                        label: 'Supuesto',
                        cell: (assumption) => assumption.label,
                    },
                    {
                        label: 'Valor al año',
                        cell: (assumption) => `${assumption.value} %`,
                        right: true,
                    },
                    {
                        label: 'Fuente',
                        cell: (assumption) => assumption.source,
                    },
                    {
                        label: 'Fijado el',
                        cell: (assumption) =>
                            describeDate(assumption.set_on) ??
                            assumption.set_on,
                    },
                ]}
            />
        </ScenarioSection>
    );
}
