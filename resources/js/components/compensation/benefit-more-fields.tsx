import BenefitGradesField from '@/components/compensation/benefit-grades-field';
import CheckField from '@/components/compensation/check-field';
import FormField from '@/components/form-field';

/** What few benefits need, folded: some categories only, a minimum of service, the other bases and a cap. */
export default function BenefitMoreFields({
    gradesUrl,
    errors,
}: {
    gradesUrl: string;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <details
            className="rounded-lg border px-4 py-1"
            open={['job_grade_ids', 'min_tenure_months', 'cap'].some(
                (key) => errors[key] !== undefined,
            )}
        >
            <summary className="flex min-h-11 cursor-pointer items-center font-medium">
                Más detalles: categorías, antigüedad mínima, otras bases y tope
            </summary>
            <div className="grid gap-4 pt-2 pb-3">
                <BenefitGradesField
                    gradesUrl={gradesUrl}
                    initial={[]}
                    error={errors['job_grade_ids']}
                />
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <FormField
                        name="min_tenure_months"
                        idPrefix="benefit"
                        label="Antigüedad mínima (meses)"
                        type="number"
                        min={0}
                        max={1200}
                        defaultValue={0}
                        error={errors['min_tenure_months']}
                    />
                    <FormField
                        name="cap"
                        idPrefix="benefit"
                        label="Tope por persona (pesos, opcional)"
                        inputMode="decimal"
                        autoComplete="off"
                        error={errors['cap']}
                    />
                </div>
                <CheckField
                    name="in_payroll_tax"
                    label="Entra a la base del impuesto sobre nómina"
                    defaultChecked
                />
                <CheckField
                    name="in_ptu_base"
                    label="Entra a la base del reparto de utilidades (PTU)"
                    defaultChecked={false}
                />
                <CheckField
                    name="in_seniority_base"
                    label="Cuenta para la base de la prima de antigüedad"
                    defaultChecked={false}
                />
                <CheckField
                    name="income_tax_exempt"
                    label="Está exenta de ISR (solo se señala, no se calcula)"
                    defaultChecked={false}
                />
            </div>
        </details>
    );
}
