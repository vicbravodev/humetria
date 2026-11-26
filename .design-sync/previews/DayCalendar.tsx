import { DayCalendar } from 'humetria';

export const EvaluationCloses = () => <DayCalendar value="2026-12-14" min="2026-11-25" onPick={() => {}} />;

export const HireDate = () => <DayCalendar value="2019-03-11" max="2026-11-25" onPick={() => {}} />;

export const NoDayYet = () => <DayCalendar value="" min="2027-01-04" max="2027-01-29" onPick={() => {}} />;
