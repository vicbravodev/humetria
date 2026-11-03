/** A risk level of NOM-035 Guías II and III, from lowest to highest. */
export type RiskLevelValue = 'null' | 'low' | 'medium' | 'high' | 'very_high';

/** A level with the action Tablas 4 and 7 of the norm ask for it. */
export type RiskLevelInfo = {
    value: RiskLevelValue;
    label: string;
    action: string;
};

/** How many questionnaires fall in each level, in the order of the levels. */
export type LevelCounts = number[];

export type RiskGroup = {
    key: string;
    label: string;
    /** The category a domain belongs to (Tablas 3 y 6); null for a category. */
    category: string | null;
    levels: LevelCounts;
};

export type RiskResults = {
    answered: number;
    visible: boolean;
    final: LevelCounts;
    categories: RiskGroup[];
    domains: RiskGroup[];
};

export type AreaResult = {
    id: string;
    name: string;
    answered: number;
    final: LevelCounts;
};

export type AreaResults = {
    visible: AreaResult[];
    hidden: number;
};

export type ItemResult = {
    number: number;
    text: string;
} & (
    | {
          answered: number;
          counts: number[];
          /** How many answered it on the side that scores risk (3 or 4 points). */
          unfavorable: number;
      }
    /** Answered by fewer than the minimum: nothing of it is shown, not even how many answered. */
    | { answered: null; counts: null; unfavorable: null }
);

export type ItemSection = {
    title: string | null;
    instruction: string;
    items: ItemResult[];
};

export type ResultsCampaign = {
    id: string;
    workCenter: string;
    kindLabel: string;
    closed: boolean;
    launchedOn: string | null;
    closedOn: string | null;
    workers: number | null;
    sampleSize: number | null;
    /** The guides it applies, by short title: "Guía I", "Guía II". */
    instruments: string[];
};

/** Where the Programa de intervención stands, for the card of the results; not made yet when `exists` is false. */
export type ResultsProgramCard = {
    exists: boolean;
    state: { value: string; label: string } | null;
    meter: { done: number; total: number } | null;
};

export type Participation = {
    invited: number;
    answered: number;
};

export type ScreeningCounts = {
    answered: number;
    requiring: number;
};

/** Where a person's channeling stands once recorded; no record means still pending. */
export type ReferralStatus = 'referred' | 'attended';

/** The record of a person's channeling (num. 5.5, 5.8 c): the day is Y-m-d for the picker, and written for reading. */
export type ScreeningReferral = {
    status: ReferralStatus;
    statusLabel: string;
    referredOn: string;
    referredOnLabel: string;
    institution: string;
};

/** How many of who requires a clinical assessment are pending, referred and attended. */
export type ReferralCounts = {
    pending: number;
    referred: number;
    attended: number;
};

/** A row of a distribution table: a group and its questionnaires by level. */
export type DistributionRow = {
    key: string;
    label: string;
    answered?: number;
    levels: LevelCounts;
};

/** A person whose Guía I requires a clinical assessment (num. 5.5). */
export type ScreenedPerson = {
    id: string;
    name: string;
    area: string;
    jobTitle: string;
    answeredOn: string;
    referral: ScreeningReferral | null;
};

/** The work center as the report names it (num. 7.7 a). */
export type ReportCenter = {
    legalName: string;
    rfc: string;
    name: string;
    address: string;
    mainActivity: string;
};

/** An action of the Programa as the report prints it: no file, no note of a step. */
export type ReportProgramAction = {
    title: string;
    kind: string;
    level: string;
    subjects: string;
    startsOn: string | null;
    dueOn: string;
    responsible: string;
    status: string;
    professional: string | null;
    overdue: boolean;
};

/** A row of the Programa as the report prints it. */
export type ReportProgramItem = {
    label: string;
    scope: string;
    level: string | null;
    analysis: string | null;
    followUp: string | null;
    actions: ReportProgramAction[];
};

/** The Programa de intervención as of the day the report is read (num. 7.7 g). */
export type ReportProgram = {
    state: string;
    stateKey: string;
    meter: { done: number; total: number };
    highestLevel: string | null;
    responsible: string | null;
    asOf: string;
    items: ReportProgramItem[];
};

/** What incise g says of the Programa: it is there, the norm asks for none, it is missing, or nothing can be derived. */
export type ReportInterventionState =
    | 'program'
    | 'not_required'
    | 'missing'
    | 'below_minimum';

export type ReportIntervention = {
    state: ReportInterventionState;
    program: ReportProgram | null;
};

/** The report of a closed evaluation (num. 7.7). What HR has not filled in yet is null. */
export type NomReport = {
    center: ReportCenter;
    guide: string;
    /** How many items the guide has: 46 for the Guía II, 72 for the III. */
    itemCount: number;
    period: string;
    participation: Participation;
    method: string;
    objective: string;
    activities: string;
    conclusions: string | null;
    recommendations: string | null;
    responsible: { name: string; license: string | null } | null;
    complete: boolean;
    results: RiskResults;
    areas: AreaResults;
    intervention: ReportIntervention;
};

/** What HR fills in for the report. */
export type ReportForm = {
    objective: string;
    activities: string;
    conclusions: string;
    recommendations: string;
    responsible_name: string;
    responsible_license: string;
};

/** A temporary link just opened: shown once, in the clear. */
export type ReportLink = {
    url: string;
    expiresAt: string;
};

/** A link to the report that still opens. */
export type ReportShareRow = {
    id: string;
    createdAt: string;
    expiresAt: string;
};

/** A duration a link may last. */
export type ShareDurationOption = {
    value: string;
    label: string;
};
