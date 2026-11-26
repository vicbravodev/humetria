import { Calendar } from 'humetria';

const weekend = (day: Date) => day.getDay() === 0 || day.getDay() === 6;

export const ClosingDay = () => (
    <Calendar mode="single" selected={new Date(2026, 11, 14)} defaultMonth={new Date(2026, 11, 1)} />
);

export const MonthAndYearMenus = () => (
    <Calendar
        mode="single"
        captionLayout="dropdown"
        selected={new Date(2019, 2, 11)}
        defaultMonth={new Date(2019, 2, 1)}
        startMonth={new Date(2000, 0, 1)}
        endMonth={new Date(2026, 10, 1)}
    />
);

export const VacationDays = () => (
    <Calendar
        mode="range"
        selected={{ from: new Date(2026, 11, 21), to: new Date(2026, 11, 24) }}
        defaultMonth={new Date(2026, 11, 1)}
        disabled={[weekend, new Date(2026, 11, 25)]}
    />
);

export const ClosedBeforeToday = () => (
    <Calendar mode="single" defaultMonth={new Date(2026, 10, 1)} disabled={{ before: new Date(2026, 10, 25) }} />
);
