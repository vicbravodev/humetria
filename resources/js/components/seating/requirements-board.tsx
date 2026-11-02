import RequirementRow from '@/components/seating/requirement-row';
import { summaryText } from '@/lib/seating/requirements';
import type { CenterRequirement, RequirementsSummary } from '@/types';

/**
 * The thirteen requirements of the center at a glance (pantalla Eval-Activa): a sentence with how many are met and the list
 * with each state. They are counts of what was registered, never an «apto».
 */
export default function RequirementsBoard({
    workCenterId,
    requirements,
    summary,
}: {
    workCenterId: string;
    requirements: CenterRequirement[];
    summary: RequirementsSummary;
}) {
    return (
        <section
            aria-labelledby="requirements-title"
            className="grid gap-4 card p-6"
        >
            <div>
                <h2
                    id="requirements-title"
                    className="text-xl leading-7 font-semibold tracking-[-0.01em]"
                >
                    Requisitos del centro
                </h2>
                <p className="text-muted-foreground tabular-nums">
                    {summaryText(summary)}
                </p>
            </div>
            <ul className="@container divide-y">
                {requirements.map((requirement) => (
                    <RequirementRow
                        key={requirement.key}
                        workCenterId={workCenterId}
                        requirement={requirement}
                    />
                ))}
            </ul>
            <p className="text-sm text-muted-foreground">
                Es un conteo de lo registrado en Humetría: no es una constancia
                de cumplimiento ante la autoridad.
            </p>
        </section>
    );
}
