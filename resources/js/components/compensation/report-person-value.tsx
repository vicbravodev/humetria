import ReportTable from '@/components/compensation/report-table';
import ScenarioSection from '@/components/compensation/scenario-section';
import { Badge } from '@/components/ui/badge';
import type { ReportPersonValue as PersonValue } from '@/types';

/** For a union: what each concept is worth to the person, gross, against what it costs the company, to decide where to insist. */
export default function ReportPersonValue({ value }: { value: PersonValue }) {
    return (
        <ScenarioSection
            title="Valor para la persona y costo para la empresa"
            description={`Por concepto, en el primer año. El valor es bruto: no se calcula el ISR de la persona y una prestación exenta solo se señala. De cada concepto que recibe la persona (${value.person_total_text}) la empresa paga ${value.company_total_text} con sus cuotas.`}
        >
            <ReportTable
                caption="Valor bruto para la persona contra costo para la empresa"
                rows={value.rows}
                rowKey={(row) => `${row.type}:${row.label}`}
                columns={[
                    {
                        label: 'Concepto',
                        cell: (row) => (
                            <>
                                {row.label}
                                {row.exempt && (
                                    <Badge variant="outline" className="ml-2">
                                        Exenta de ISR
                                    </Badge>
                                )}
                            </>
                        ),
                    },
                    {
                        label: 'Valor bruto para la persona',
                        cell: (row) => row.person_text ?? '—',
                        right: true,
                    },
                    {
                        label: 'Costo para la empresa',
                        cell: (row) => row.company_text,
                        right: true,
                    },
                ]}
            />
        </ScenarioSection>
    );
}
