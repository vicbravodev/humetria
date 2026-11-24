import BarsDiagram from '@/components/blog/diagrams/bars-diagram';

const ROWS = [
    { label: '2026', value: 9 },
    { label: '2027', value: 9 },
    { label: '2028', value: 10 },
    { label: '2029', value: 11 },
    { label: '2030', value: 12 },
] as const;

/** The weekly cap of overtime going up from 9 to 12 hours: the other side of the same calendar. */
export default function TopesHorasExtra() {
    return (
        <BarsDiagram
            title="El tope de horas extra por semana sube mientras la jornada baja"
            source="Artículo 66 de la LFT y cuarto transitorio del decreto: el tope de 12 horas se alcanza poco a poco."
            rows={ROWS}
            max={12}
            unit="h"
        />
    );
}
