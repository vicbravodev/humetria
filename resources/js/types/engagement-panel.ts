export type ClimateSurveyStatus = 'draft' | 'active' | 'closed';

/** What a pulse looks at this time (Seguimiento, Recorrido or Jefe). */
export type PulseModeOption = { value: string; label: string };

/** When the pulse of the company goes out by itself, and what the next one will be. */
export type PulseSchedule = {
    frequency: string;
    frequencyLabel: string;
    dayOfMonth: number;
    openDays: number;
    active: boolean;
    nextRunOn: string;
    /** The mode RH chose for the next pulse only; null when the calendar decides. */
    nextMode: string | null;
    upcoming: { mode: string; modeLabel: string; items: number };
};

/** A survey as the panel shows it: only counts, never who answered. */
export type ClimateSurveyCard = {
    id: string;
    name: string;
    /** The mode of a pulse; null for a climate survey. */
    mode: PulseModeOption | null;
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
    /** What a pulse looked at («Recorrido»); nothing for climate. */
    mode: string | null;
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

/** What the previous survey left open: the count and the three closest to their date. */
export type OpenCommitments = {
    count: number;
    surveyId: string;
    next: { id: string; will_do: string; owner_name: string; due_on: string }[];
};

/** Someone with no email, who will not receive their link: RH knows who, the survey never does. */
export type ClimateUnreachable = { id: string; name: string; area: string };

/** A section of the questionnaire as the people will read it. */
export type ClimateSection = { title: string; items: { text: string }[] };

/** A group a participation cut shows: the people invited to it and how many answered, never who. */
export type ClimateGroup = { label: string; invited: number; answered: number };

/** Participation by one attribute; only the groups big enough to show are in it. */
export type ClimateCut = {
    key: 'work_center' | 'area' | 'tenure';
    title: string;
    groups: ClimateGroup[];
};

/** How far a survey has come: counts of people, never who. */
export type ClimateParticipation = {
    invited: number;
    answered: number;
    rate: number | null;
    pending: number;
    withoutLink: number;
    canRemind: boolean;
    minGroup: number;
    cuts: ClimateCut[];
};

/** The arrow of a point against the one before it; null when either wave was too small for it. */
export type TrendDirection = 'up' | 'down' | 'same' | null;

/** A wave of a series: its month, its value and the arrow. Nothing of who answered. */
export type TrendPoint = {
    surveyId: string;
    label: string;
    value: number;
    direction: TrendDirection;
};

/** A closed survey on the axis of the trend: its month, never its day. */
export type TrendWave = { surveyId: string; label: string };

/** A figure of the company across its closed surveys. */
export type TrendSeries = {
    key: string;
    title: string;
    hint: string | null;
    kind: 'percent' | 'index';
    points: TrendPoint[];
};
