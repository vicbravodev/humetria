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
    cancelled: number;
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
