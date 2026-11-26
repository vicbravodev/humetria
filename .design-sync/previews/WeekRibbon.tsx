import { WeekRibbon } from 'humetria';

const h = (hours: number, extra = 0) => ({ worked: Math.round((hours + extra) * 60), overtime: Math.round(extra * 60) });

export const InRow = () => (
    <div className="flex items-center gap-3">
        <WeekRibbon days={[h(8), h(8), h(8.5), h(8), h(8), h(5), h(0)]} />
        <div className="flex flex-col whitespace-nowrap tabular-nums">
            <span className="font-semibold">45 h 30 min</span>
            <span className="text-caption text-muted-foreground">Sin horas extra</span>
        </div>
    </div>
);

export const Overtime = () => (
    <div className="flex items-center gap-3">
        <WeekRibbon days={[h(8, 2), h(8, 3), h(8), h(8, 5), h(8, 1), h(6), h(0)]} />
        <div className="flex flex-col whitespace-nowrap tabular-nums">
            <span className="font-semibold">57 h</span>
            <span className="text-caption text-muted-foreground">11 h extra</span>
        </div>
    </div>
);

export const CardLabeled = () => (
    <WeekRibbon size="md" labeled days={[h(9), h(9), h(9), h(9), h(8), h(0), h(0)]} />
);

export const Vacation = () => (
    <WeekRibbon
        size="md"
        labeled
        days={[h(8), h(8), { worked: 0, overtime: 0, absent: true }, { worked: 0, overtime: 0, absent: true }, { worked: 0, overtime: 0, absent: true }, h(0), h(0)]}
    />
);

export const Empty = () => <WeekRibbon days={[]} />;
