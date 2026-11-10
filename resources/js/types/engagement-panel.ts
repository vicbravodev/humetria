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

/** What RH reviews on a draft before launching it. */
export type ClimateDraft = {
    people: number;
    withoutEmail: number;
    /** People reported only with the whole company: every group they could be in is too small. */
    companyOnly: number;
    minGroup: number;
    canLaunch: boolean;
    limits: { min: string; suggested: string; max: string };
};

/** Someone with no email, who will not receive their link: RH knows who, the survey never does. */
export type ClimateUnreachable = { id: string; name: string; area: string };

/** A section of the questionnaire as the people will read it. */
export type ClimateSection = { title: string; items: { text: string }[] };
