import ScenarioSection from '@/components/compensation/scenario-section';
import FormField from '@/components/form-field';
import PesosField from '@/components/pesos-field';
import PillChoice from '@/components/humetria/pill-choice';
import type { ScenarioPayroll } from '@/types';

const GROUPS: { value: ScenarioPayroll['average_group']; label: string }[] = [
    { value: 'unionized', label: 'Sindicalizados' },
    { value: 'trusted', label: 'Confianza' },
];

/**
 * «La plantilla promedio» (Cotizador rápido): in place of the roster and the tabulator, how many people there are, what they earn
 * a month on average, how long they have served (it decides their days of vacation) and which group they are.
 */
export default function ScenarioPayrollCard({
    payroll,
    group,
    onGroup,
    errors,
}: {
    /** The values it starts with: the scenario's, or the defaults of a new one. */
    payroll: ScenarioPayroll;
    group: ScenarioPayroll['average_group'];
    onGroup: (group: ScenarioPayroll['average_group']) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <ScenarioSection
            title="La plantilla promedio"
            description="Dime cuántas personas son y cuánto ganan en promedio, y ve lo que cuesta de verdad el aumento. Sin cargar plantilla ni tabulador."
        >
            <div className="grid items-start gap-5 sm:grid-cols-2">
                <FormField
                    name="average_workers"
                    idPrefix="scenario"
                    label="Personas trabajadoras"
                    inputMode="numeric"
                    autoComplete="off"
                    placeholder="500"
                    defaultValue={payroll.average_workers}
                    error={errors['average_workers']}
                />
                <PesosField
                    name="average_monthly_salary"
                    idPrefix="scenario"
                    label="Sueldo mensual promedio"
                    placeholder="15,000.00"
                    defaultValue={payroll.average_monthly_salary}
                    error={errors['average_monthly_salary']}
                />
                <FormField
                    name="average_tenure_years"
                    idPrefix="scenario"
                    label="Años de servicio promedio"
                    inputMode="numeric"
                    autoComplete="off"
                    defaultValue={payroll.average_tenure_years}
                    error={errors['average_tenure_years']}
                />
            </div>
            <PillChoice
                name="average_group"
                legend="¿Qué grupo son?"
                options={GROUPS}
                value={group}
                onChange={onGroup}
            />
            <p className="text-sm text-muted-foreground">
                El sueldo mensual se divide entre 30 para el salario diario y el
                año se paga en 12 meses (art. 29 de la LSS). Las prestaciones
                son las de tu contrato; si no has capturado ninguna, las de ley.
            </p>
        </ScenarioSection>
    );
}
