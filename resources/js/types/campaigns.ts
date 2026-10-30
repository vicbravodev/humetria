import type { Paginated } from '@/types/demos';

export type CampaignStatus = 'draft' | 'active' | 'closed';

export type CampaignKind = 'nom035_evaluation' | 'nom035_trauma';

/** Answers of the guide that measures the campaign, against its sample or everyone invited. */
export type CampaignProgress = {
    completed: number;
    target: number;
    againstSample: boolean;
};

export type CampaignSummary = {
    id: string;
    workCenter: string;
    kind: CampaignKind;
    kindLabel: string;
    status: CampaignStatus;
    statusLabel: string;
    /** Short titles, in order: "Guía I", "Guía III". */
    instruments: string[];
    closesOn: string | null;
    closedOn: string | null;
    progress: CampaignProgress | null;
};

export type GenderQuota = { gender: string; label: string; count: number };

export type CampaignDetail = CampaignSummary & {
    workers: number | null;
    population: 'all' | 'sample' | null;
    populationLabel: string | null;
    sampleSize: number | null;
    quotas: GenderQuota[];
    launchedOn: string | null;
};

export type InstrumentProgress = {
    key: string;
    title: string;
    invited: number;
    completed: number;
    /** Pending with a link sent. */
    pending: number;
    /** Pending with no link: no email. */
    unreachable: number;
    cancelled: number;
};

/** The last batch of survey links while the queue sends it: how many it went through, of all. */
export type CampaignSending = { done: number; total: number };

/** How many people of a campaign stand somewhere, as a choice of the people filter. */
export type StandingCount = { value: string; label: string; count: number };

/** How the people of a campaign are narrowed, as the server sends it back. */
export type PeopleFilters = {
    status: string;
    guide: string | null;
    area: { value: string; label: string } | null;
    q: string;
};

/** Who can be followed up: a page of people, where they stand, and the filters that narrowed them. */
export type PeoplePage = {
    people: Paginated<CampaignPerson>;
    standings: StandingCount[];
    filters: PeopleFilters;
};

export type SampledPerson = {
    id: string;
    name: string;
    area: string;
    gender: string;
};

/** A work center as the NOM-035 page lists it: who works there, what applies, and when it was last evaluated. */
export type WorkCenterEvaluation = {
    id: string;
    name: string;
    workers: number;
    guides: ('I' | 'II' | 'III')[];
    lastEvaluatedOn: string | null;
    dueOn: string | null;
    overdue: boolean;
    openCampaignId: string | null;
    eligible: boolean;
};

export type SurveyOption = { value: string; label: string };

export type SurveyItem = { number: number; text: string };

export type SurveyGate = {
    key: 'serves_clients' | 'supervises';
    question: string;
    instruction: string;
};

export type SurveySection = {
    title: string | null;
    instruction: string;
    gate: SurveyGate | null;
    items: SurveyItem[];
};

/** A guide as the person answers it, literal from the norm. */
export type SurveyInstrument = {
    key: string;
    title: string;
    shortTitle: string;
    /** Guía I carries the person's name; Guías II and III never do. */
    identified: boolean;
    status: 'pending' | 'completed';
    options: SurveyOption[];
    sections: SurveySection[];
};

export type SurveyUnavailable = 'invalid' | 'closed' | 'done' | 'suspended';

export type ParticipantGuide = {
    key: string;
    title: string;
    status: 'pending' | 'completed' | 'cancelled';
    statusLabel: string;
};

/** A person invited to a campaign: whether they answered each guide, never what. */
export type CampaignPerson = {
    id: string;
    name: string;
    area: string;
    reachable: boolean;
    inSample: boolean;
    guides: ParticipantGuide[];
};

/** A person who can be chosen for a Guía I campaign. */
export type RosterPerson = {
    id: string;
    name: string;
    area: string;
    reachable: boolean;
};

/** People of one gender left outside a sample, who can replace someone of that gender. */
export type ReplacementPool = {
    gender: string;
    label: string;
    available: number;
};
