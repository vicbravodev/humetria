import type { RiskLevelValue } from './results';
import type { Paginated } from './demos';

/** One of a short fixed list: what is sent and what is read. */
export type Choice<V extends string | number = string> = {
    value: V;
    label: string;
};

/** A risk level as Results names it: its key and its written name. */
export type ProgramLevel = { value: RiskLevelValue; label: string };

export type ProgramScope = 'final' | 'category' | 'domain';

export type ActionStatusValue =
    | 'planned'
    | 'in_progress'
    | 'done'
    | 'cancelled';

export type ProgramStateValue = 'none' | 'in_progress' | 'overdue' | 'done';

/** The evaluation that came after, to follow up the measures of a row (num. 8.4 e). */
export type FollowUpEvaluation = { id: string; label: string };

export type ProgramFollowUpState = {
    dueOn: string | null;
    dueOnLabel: string | null;
    doneOn: string | null;
    doneOnLabel: string | null;
    result: string | null;
    evaluation: FollowUpEvaluation | null;
};

/** One row of the Programa: the final score, a category or a domain it attends. No count of questionnaires. */
export type ProgramRow = {
    id: string;
    scope: ProgramScope;
    label: string;
    category: string | null;
    /** The highest level that led to the row; null on a row HR added. */
    level: ProgramLevel | null;
    manual: boolean;
    state: Choice<'none' | 'in_progress' | 'done'>;
    /** The actions of the row that are done, of those not cancelled. */
    meter: { done: number; total: number };
    analysis: string | null;
    missingAnalysis: boolean;
    followUp: ProgramFollowUpState;
};

/** A line of «Lo que pide la Tabla»: covered or missing, never a verdict. */
export type Requirement = {
    key: string;
    text: string;
    status: 'covered' | 'missing' | 'optional';
    detail: string | null;
};

export type ProgramData = {
    id: string;
    highestLevel: ProgramLevel | null;
    responsible: { name: string; userId: number | null } | null;
    state: Choice<ProgramStateValue>;
    meter: { done: number; total: number };
    /** How many actions are past their due day and not finished. */
    overdue: number;
    requirements: Requirement[];
    items: ProgramRow[];
};

/** Why an evaluation has no Programa yet: the results ask for one, ask for none, or are under the minimum. */
export type ProgramRequirement = 'required' | 'not_required' | 'below_minimum';

export type ProgramCampaign = {
    id: string;
    workCenterId: string;
    workCenter: string;
    kindLabel: string;
    closedOn: string | null;
    guide: string | null;
};

/** One step of an action (num. 8.4 d). The file opens only for whoever writes the Programa. */
export type ProgressStep = {
    onLabel: string;
    status: Choice<ActionStatusValue>;
    note: string | null;
    hasEvidence: boolean;
    evidenceUrl: string | null;
};

export type ProgramAction = {
    id: string;
    itemId: string;
    itemLabel: string;
    level: Choice<number>;
    kind: Choice;
    title: string;
    description: string;
    subjects: Choice<'center' | 'areas'>;
    areas: { id: string; name: string }[];
    hiddenAreas: number;
    startsOn: string | null;
    startsOnLabel: string | null;
    dueOn: string;
    dueOnLabel: string;
    responsible: { name: string; userId: number | null };
    professional: Choice | null;
    status: Choice<ActionStatusValue>;
    overdue: boolean;
    history: ProgressStep[];
    completedOnLabel: string | null;
};

/** What the action form offers: the kinds (with the level each belongs to), the levels and the professionals. */
export type ActionCatalog = {
    kinds: { value: string; label: string; level: number | null }[];
    levels: Choice<number>[];
    professionals: Choice[];
};

/** An area Results shows, with how many of its questionnaires are in Medio or more in each row. */
export type AreaOption = {
    id: string;
    name: string;
    answered: number;
    atRisk: Record<string, number>;
};

/** A place of the guide HR may still add as a row. */
export type ManualOption = {
    scope: ProgramScope;
    key: string;
    label: string;
    category: string | null;
};

export type ProgramMember = { id: number; name: string };

export type ActionFilters = { item: string | null; status: string | null };

/** The page of a Programa: what it tells everyone, and the tools of whoever writes it (null for the rest). */
export type ProgramPageProps = {
    campaign: ProgramCampaign;
    program: ProgramData | null;
    requirement: ProgramRequirement | null;
    actions: Paginated<ProgramAction> | null;
    filters: ActionFilters;
    catalog: ActionCatalog | null;
    manualOptions: ManualOption[] | null;
    followUpEvaluations: FollowUpEvaluation[] | null;
    followUpSuggestion: { date: string; label: string } | null;
    areaOptions: AreaOption[] | null;
    members: ProgramMember[] | null;
    /** Today (Y-m-d) in the company's time zone: the day a step is recorded by default. */
    today: string;
    can: { manage: boolean };
};

export type ProgramsSummary = Record<ProgramStateValue, number>;

/** One closed evaluation of the list of Programas. */
export type ProgramListRow = {
    id: string;
    workCenter: string;
    closedOn: string | null;
    /** Whether it applies a Guía II or III: only then is it scored by risk and has a Programa to speak of. */
    applies: boolean;
    program: {
        state: Choice<ProgramStateValue>;
        meter: { done: number; total: number };
    } | null;
};

/** What whoever writes the Programa also receives: the choices of every form. */
export type ProgramTools = {
    catalog: ActionCatalog;
    areas: AreaOption[];
    members: ProgramMember[];
    manualOptions: ManualOption[];
    evaluations: FollowUpEvaluation[];
    suggestion: string | undefined;
    today: string;
    /** The name an action takes when its responsible is left empty: the Programa's. */
    responsible: string | null;
};

/** The tools of the menu of an action: each opens a dialog or a side panel. */
export type ActionTool = 'progress' | 'edit' | 'copy' | 'cancel' | 'history';
