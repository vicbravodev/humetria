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
    answered: number;
    counts: number[] | null;
};

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
};

export type Participation = {
    invited: number;
    answered: number;
};

export type ScreeningCounts = {
    answered: number;
    requiring: number;
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
    answeredOn: string;
};
