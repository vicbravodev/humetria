import { MonthGrid } from 'humetria';

export const PayrollMonth = () => <MonthGrid value="2026-11" max="2026-11" onPick={() => {}} />;

export const FromHireMonth = () => <MonthGrid value="2026-05" min="2026-03" max="2026-11" onPick={() => {}} />;

export const PreviousYear = () => <MonthGrid value="2025-12" onPick={() => {}} />;
