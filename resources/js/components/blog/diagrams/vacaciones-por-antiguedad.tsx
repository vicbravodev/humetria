import BarsDiagram from '@/components/blog/diagrams/bars-diagram';

const ROWS = [
    { label: '1 año', value: 12 },
    { label: '2 años', value: 14 },
    { label: '3 años', value: 16 },
    { label: '4 años', value: 18 },
    { label: '5 años', value: 20 },
    { label: '6 a 10', value: 22 },
    { label: '11 a 15', value: 24 },
    { label: '16 a 20', value: 26 },
    { label: '21 a 25', value: 28 },
    { label: '26 a 30', value: 30 },
    { label: '31 a 35', value: 32 },
    { label: '36 a 40', value: 34 },
] as const;

/** The days of vacation by years of service, as the article's table shows them, as bars on one scale. */
export default function VacacionesPorAntiguedad() {
    return (
        <BarsDiagram
            title="Días laborables de vacaciones según los años cumplidos"
            source="Artículo 76 de la LFT, tabla vigente."
            rows={ROWS}
            max={34}
            unit="días"
        />
    );
}
