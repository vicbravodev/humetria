import { ColumnChart, TooltipProvider } from 'humetria';

export const ScenarioYears = () => (
    <TooltipProvider>
        <div className="card p-6">
            <ColumnChart
                label="Costo adicional por año, 2026 a 2029"
                columns={[
                    { key: '1', label: '2026', valueText: '$1,701,900', tooltip: '2026: $1,701,900 · incluye el retroactivo', percent: 82.4 },
                    { key: '2', label: '2027', valueText: '$1,842,300', tooltip: '2027: $1,842,300', percent: 89.2 },
                    { key: '3', label: '2028', valueText: '$1,951,600', tooltip: '2028: $1,951,600', percent: 94.5 },
                    { key: '4', label: '2029', valueText: '$2,065,400', tooltip: '2029: $2,065,400', percent: 100 },
                ]}
            />
        </div>
    </TooltipProvider>
);

const policy = [14, 16, 18, 20, 22, 24];
const law = [12, 14, 16, 18, 20, 22];

export const LeaveAgainstLaw = () => (
    <TooltipProvider>
        <div className="card p-6">
            <ColumnChart
                label="Días por antigüedad de Política sindicalizados"
                columns={policy.map((days, index) => {
                    const last = index === policy.length - 1;
                    return {
                        key: `year-${index + 1}`,
                        label: last ? `${index + 1}–${index + 5}` : String(index + 1),
                        valueText: String(days),
                        tooltip: `${last ? `Años ${index + 1} a ${index + 5}` : `Año ${index + 1}`}: ${days} días; la ley da ${law[index]}`,
                        percent: Math.round((days / 24) * 1000) / 10,
                        referencePercent: Math.round(((law[index] ?? 0) / 24) * 1000) / 10,
                    };
                })}
            />
        </div>
    </TooltipProvider>
);

export const PulseTrend = () => (
    <TooltipProvider>
        <div className="card p-6">
            <ColumnChart
                label="Me siento reconocido por mi trabajo"
                baseline={{ percent: 54, label: 'Línea base: mar, 54 %' }}
                columns={[
                    { key: 'mar', label: 'mar', valueText: '54 %', tooltip: 'mar: 54 %', percent: 54 },
                    { key: 'may', label: 'may', valueText: '58 %', tooltip: 'may: 58 % · +4 pts', percent: 58 },
                    { key: 'jul', label: 'jul', valueText: '61 %', tooltip: 'jul: 61 % · +3 pts', percent: 61 },
                    { key: 'sep', label: 'sep', valueText: '57 %', tooltip: 'sep: 57 % · −4 pts', percent: 57 },
                    { key: 'nov', label: 'nov', valueText: '66 %', tooltip: 'nov: 66 % · +9 pts', percent: 66 },
                ]}
            />
        </div>
    </TooltipProvider>
);
