import type { RegulationRecord } from '@/types';

const LABELS: [keyof RegulationRecord['parts'], string][] = [
    ['risk_factors', 'Factores de riesgo'],
    ['pauses', 'Pausas'],
    ['seat_location', 'Ubicación y disponibilidad de los asientos'],
    ['usage_rule', 'Norma de uso del asiento'],
];

/** The four parts as text, for the signed regulation, for the Comisión that only reads it, and for paper. */
export default function RegulationPartsView({
    parts,
}: {
    parts: RegulationRecord['parts'];
}) {
    return (
        <div className="grid gap-5">
            {LABELS.map(([key, label]) => (
                <section key={key} className="grid gap-1.5">
                    <h2 className="text-base font-semibold">{label}</h2>
                    <p className="whitespace-pre-line">{parts[key]}</p>
                </section>
            ))}
        </div>
    );
}
