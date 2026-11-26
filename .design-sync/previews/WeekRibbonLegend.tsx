import { WeekRibbon, WeekRibbonLegend } from 'humetria';

const h = (hours: number, extra = 0) => ({ worked: Math.round((hours + extra) * 60), overtime: Math.round(extra * 60) });

export const Legend = () => <WeekRibbonLegend />;

export const UnderRibbons = () => (
    <div className="flex flex-col gap-4">
        <div className="card flex flex-col">
            {[
                { name: 'Mariana Treviño', days: [h(8), h(8), h(8.5), h(8), h(8), h(5), h(0)], total: '45 h 30 min', note: 'Sin horas extra' },
                { name: 'Jorge Salinas', days: [h(8, 2), h(8, 3), h(8), h(8, 5), h(8, 1), h(6), h(0)], total: '57 h', note: '11 h extra' },
                { name: 'Lucía Garza', days: [h(8), h(8), { worked: 0, overtime: 0, absent: true }, { worked: 0, overtime: 0, absent: true }, h(8), h(0), h(0)], total: '24 h', note: '2 días de vacaciones' },
            ].map((person) => (
                <div key={person.name} className="flex items-center justify-between gap-4 border-b px-6 py-3 last:border-b-0">
                    <span className="font-medium">{person.name}</span>
                    <div className="flex items-center gap-3">
                        <WeekRibbon days={person.days} />
                        <div className="flex flex-col whitespace-nowrap tabular-nums">
                            <span className="font-semibold">{person.total}</span>
                            <span className="text-caption text-muted-foreground">{person.note}</span>
                        </div>
                    </div>
                </div>
            ))}
        </div>
        <WeekRibbonLegend />
    </div>
);
