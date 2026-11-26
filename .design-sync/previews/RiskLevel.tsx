import { RiskLevel } from 'humetria';

export const AllLevels = () => (
    <div className="flex flex-col items-start gap-3">
        <RiskLevel level="null" label="Nulo" />
        <RiskLevel level="low" label="Bajo" />
        <RiskLevel level="medium" label="Medio" />
        <RiskLevel level="high" label="Alto" />
        <RiskLevel level="very_high" label="Muy alto" />
    </div>
);

export const Large = () => <RiskLevel level="high" label="Riesgo alto" size="lg" />;

export const InASentence = () => (
    <p className="flex items-center gap-2 text-base">
        Calificación final del centro: <RiskLevel level="medium" label="Medio" size="sm" />
    </p>
);
