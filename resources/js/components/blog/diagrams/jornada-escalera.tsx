import BarsDiagram from '@/components/blog/diagrams/bars-diagram';

const ROWS = [
    { label: '2026', value: 48 },
    { label: '2027', value: 46 },
    { label: '2028', value: 44 },
    { label: '2029', value: 42 },
    { label: '2030', value: 40 },
] as const;

/** The ordinary weekly maximum coming down from 48 to 40 hours, year by year. */
export default function JornadaEscalera() {
    return (
        <BarsDiagram
            title="La jornada ordinaria máxima baja cada año, hasta 40 horas por semana"
            source="Segundo transitorio del decreto de la reforma. Cada cambio entra el 1 de enero."
            rows={ROWS}
            max={48}
            unit="h"
        />
    );
}
