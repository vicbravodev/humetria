import type { FormEvent } from 'react';
import ReportField from '@/components/results/report-field';
import type { ReportForm } from '@/types';

/** The error of a field goes as soon as it is edited, not when the answer of the next save arrives. */
function clearEdited(
    event: FormEvent,
    clearErrors: (...fields: string[]) => void,
): void {
    if (
        event.target instanceof HTMLInputElement ||
        event.target instanceof HTMLTextAreaElement
    ) {
        clearErrors(event.target.name);
    }
}

/** The six parts of the report HR writes, each with the error of the last save. */
export default function ReportFields({
    form,
    errors,
    clearErrors,
}: {
    form: ReportForm;
    errors: Partial<Record<string, string>>;
    clearErrors: (...fields: string[]) => void;
}) {
    return (
        <div
            className="grid gap-6"
            onInput={(event) => clearEdited(event, clearErrors)}
        >
            <ReportField
                name="objective"
                label="Objetivo"
                value={form.objective}
                error={errors['objective']}
                multiline
            />
            <ReportField
                name="activities"
                label="Principales actividades realizadas en el centro"
                value={form.activities}
                error={errors['activities']}
                multiline
            />
            <ReportField
                name="conclusions"
                label="Conclusiones"
                value={form.conclusions}
                error={errors['conclusions']}
                multiline
            />
            <ReportField
                name="recommendations"
                label="Recomendaciones y acciones de intervención"
                value={form.recommendations}
                error={errors['recommendations']}
                multiline
            />
            <ReportField
                name="responsible_name"
                label="Responsable de la evaluación"
                value={form.responsible_name}
                error={errors['responsible_name']}
                hint="Nombre completo."
            />
            <ReportField
                name="responsible_license"
                label="Cédula profesional (opcional)"
                value={form.responsible_license}
                error={errors['responsible_license']}
            />
        </div>
    );
}
