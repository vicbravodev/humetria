import { ListChecks } from 'lucide-react';
import RiskLevel from '@/components/humetria/risk-level';
import SettingRow from '@/components/humetria/setting-row';
import type { AssessmentRecord, MeasureSuggestions } from '@/types';

/** The signed result: total, level, when, and the measures Humetría suggests for it, labelled as a suggestion. */
export default function AssessmentResult({
    assessment,
    suggestions,
}: {
    assessment: AssessmentRecord;
    suggestions: MeasureSuggestions | null;
}) {
    return (
        <SettingRow
            icon={ListChecks}
            title="Resultado"
            description={`Firmada el ${assessment.signedOn ?? ''} con la edición ${assessment.version ?? ''} de las tablas del DOF.`}
        >
            <div className="flex flex-wrap items-center gap-3">
                <span className="text-3xl font-semibold tabular-nums">
                    {assessment.total} de 21
                </span>
                {assessment.level !== null &&
                    assessment.levelLabel !== null && (
                        <RiskLevel
                            level={assessment.level}
                            label={assessment.levelLabel}
                        />
                    )}
            </div>
            {assessment.reassessmentDue && (
                <p className="text-sm text-muted-foreground">
                    Algo cambió desde que se firmó: conviene evaluar de nuevo.
                </p>
            )}
            {suggestions !== null && (
                <div className="flex flex-col gap-1">
                    <p className="text-caption text-muted-foreground">
                        {suggestions.label}
                    </p>
                    <ul className="list-disc pl-5">
                        {suggestions.measures.map((measure) => (
                            <li key={measure.value}>{measure.label}</li>
                        ))}
                    </ul>
                </div>
            )}
        </SettingRow>
    );
}
