import { RangeCalendar } from 'humetria';

export const VacationPeriod = () => <RangeCalendar from="2026-12-21" to="2026-12-31" onPick={() => {}} />;

export const FirstDayPicked = () => <RangeCalendar from="2026-12-07" to="" min="2026-11-25" onPick={() => {}} />;

export const WithinTheYear = () => (
    <RangeCalendar from="2026-11-02" to="2026-11-13" min="2026-11-01" max="2026-11-25" onPick={() => {}} />
);
