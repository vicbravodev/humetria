/** Someone who can sit on a work center's Comisión, with the role they hold in the company. */
export type CommitteePerson = {
    id: number;
    name: string;
    email: string;
    role: string;
    /** False when they no longer hold a reviewing role: the seat is kept so RH can take it away. */
    active: boolean;
};

export type Committee = {
    /** The day of the constitution act, Y-m-d. */
    constitutedOn: string;
    hasAct: boolean;
    members: CommitteePerson[];
};

export type QuestionOrigin = 'dof' | 'dof_modified' | 'humetria' | 'committee';

export type FavorableAnswer = 'yes' | 'no' | 'informative';

/** One question of a checklist version, as the Comisión sees and edits it. */
export type ChecklistQuestion = {
    id: string;
    section: number;
    code: string;
    heading: string;
    text: string;
    origin: QuestionOrigin;
    favorable: FavorableAnswer;
    /** A value of the requirement list (7.1a…), or null when it covers none. */
    requirement: string | null;
    sensitive: boolean;
};

export type RequirementOption = { value: string; label: string };

/** A checklist in a person's portal. `label` never says how the Comisión judged it. */
export type PortalChecklist = {
    id: string;
    workplace: string;
    section: number;
    label: string;
};

export type PortalPending = {
    /** Still to answer. */
    checklists: PortalChecklist[];
    /** Sent, waiting for the Comisión. */
    sent: PortalChecklist[];
};

export type TeleworkerStatus = 'aspirant' | 'active' | 'ended';

/** A place where a person teleworks, with where its first checklist stands. */
export type TeleworkerWorkplace = {
    id: string;
    address: string;
    label: string;
};

export type TeleworkerSummary = {
    id: string;
    name: string;
    jobTitle: string;
    hasEmail: boolean;
    status: TeleworkerStatus;
    percentage: number;
    workplaces: TeleworkerWorkplace[];
};

/** A question as the person sees it in their portal. */
export type PortalQuestion = {
    id: string;
    heading: string;
    text: string;
    favorable: FavorableAnswer;
    sensitive: boolean;
};

export type PortalAnswer = 'yes' | 'no' | 'not_applicable';

export type PortalApplication = {
    id: string;
    section: number;
    workplace: string;
};
