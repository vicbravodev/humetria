export type ClimateSurveyStatus = 'draft' | 'active' | 'closed';

/** A survey as the panel shows it: only counts, never who answered. */
export type ClimateSurveyCard = {
    id: string;
    name: string;
    status: ClimateSurveyStatus;
    statusLabel: string;
    closesOn: string | null;
    daysLeft: number | null;
    invited: number | null;
    answered: number | null;
    rate: number | null;
};

/** A row of the history: a closed survey and how many answered it. */
export type ClimateSurveyHistoryRow = {
    id: string;
    name: string;
    closedOn: string | null;
    invited: number | null;
    answered: number;
    rate: number | null;
};
