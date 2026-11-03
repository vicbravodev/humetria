import type { RiskLevelValue } from './results';

/** A risk level as Results names it: its key and its written name. */
export type ProgramLevel = { value: RiskLevelValue; label: string };

export type ProgramScope = 'final' | 'category' | 'domain';

/** One row of the Programa: the final score, a category or a domain it attends. No count of questionnaires. */
export type ProgramRow = {
    id: string;
    scope: ProgramScope;
    label: string;
    category: string | null;
    /** The highest level that led to the row; null on a row HR added. */
    level: ProgramLevel | null;
    manual: boolean;
};

export type ProgramData = {
    id: string;
    highestLevel: ProgramLevel | null;
    responsible: string | null;
    items: ProgramRow[];
};

/** Why an evaluation has no Programa yet: the results ask for one, ask for none, or are under the minimum. */
export type ProgramRequirement = 'required' | 'not_required' | 'below_minimum';

export type ProgramCampaign = {
    id: string;
    workCenter: string;
    kindLabel: string;
    closedOn: string | null;
    guide: string | null;
};
