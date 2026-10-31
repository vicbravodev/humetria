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

/** Photos the Comisión asked the person for. `note` says what to photograph. */
export type PortalPhotoRequest = {
    id: string;
    workplace: string;
    note: string;
};

/** A visit the Comisión asked the person to authorize. */
export type PortalVisit = {
    id: string;
    workplace: string;
};

export type PortalPending = {
    /** Still to answer. */
    checklists: PortalChecklist[];
    /** Sent: waiting for the Comisión, or reviewed. */
    sent: PortalChecklist[];
    photos: PortalPhotoRequest[];
    visits: PortalVisit[];
};

export type TeleworkerStatus = 'aspirant' | 'active' | 'ended';

/** A place where a person teleworks, with where its first checklist stands. */
export type TeleworkerWorkplace = {
    id: string;
    address: string;
    label: string;
    /** Derived by the server from the latest review; never edited by hand. */
    status: WorkplaceStatusValue;
    statusLabel: string;
    /** The first day the validation no longer holds, Y-m-d. */
    expiresOn: string | null;
};

export type EndReasonValue =
    | 'reversibility'
    | 'left_company'
    | 'transfer'
    | 'other';

export type TeleworkerSummary = {
    id: string;
    name: string;
    jobTitle: string;
    hasEmail: boolean;
    status: TeleworkerStatus;
    percentage: number;
    /** An aspirant with at least one workplace validated and in force. */
    canJoin: boolean;
    /** Today in the company's time zone, Y-m-d. */
    today: string;
    startedOn: string | null;
    noEquipmentReason: string | null;
    /** How it ended, already worded; null while the period is open. */
    endReason: string | null;
    endedOn: string | null;
    endNote: string | null;
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

/** A submitted checklist waiting in the Comisión's inbox. */
export type ReviewRow = {
    id: string;
    person: string;
    jobTitle: string;
    workplace: string;
    section: number;
    /** The day it was sent, Y-m-d. */
    submittedOn: string | null;
};

export type ReviewDecisionValue = 'valid' | 'valid_with_plan' | 'not_valid';

export type WorkplaceStatusValue =
    | 'pending'
    | 'valid'
    | 'valid_with_plan'
    | 'not_valid'
    | 'retired';

/** A photo, by id: it is shown through the authorized route, never by a public address. */
export type ReviewPhoto = { id: string };

export type ReviewAnswer = {
    id: string;
    code: string;
    heading: string;
    text: string;
    /** yes, no, not_applicable, or null when a sensitive question was left unanswered. */
    answer: string | null;
    note: string | null;
    photos: ReviewPhoto[];
};

export type ReviewAnswers = {
    unfavorable: ReviewAnswer[];
    other: ReviewAnswer[];
    informative: ReviewAnswer[];
    generalPhotos: ReviewPhoto[];
};

/** A decision of the Comisión about a workplace. Dates are Y-m-d. */
export type ReviewDecisionRecord = {
    decision: ReviewDecisionValue;
    decisionLabel: string;
    methodLabel: string;
    decidedOn: string;
    visitedOn: string | null;
    notes: string;
    actionPlan: string | null;
    planDueOn: string | null;
};

export type ReviewEvidence = {
    id: string;
    kind: 'photos' | 'remote_check';
    note: string;
    requestedOn: string;
    fulfilled: boolean;
    photos: ReviewPhoto[];
};

export type ReviewAuthorization = {
    id: string;
    requestedOn: string;
    authorized: boolean;
    cancelled: boolean;
    viaPortal: boolean;
    hasDocument: boolean;
    used: boolean;
};

export type ReviewDetail = {
    application: {
        id: string;
        section: number;
        submittedOn: string | null;
        consentAccepted: boolean;
        specialNeeds: string | null;
    };
    person: { name: string; jobTitle: string };
    workplace: {
        id: string;
        address: string;
        status: WorkplaceStatusValue;
        statusLabel: string;
        expiresOn: string | null;
    };
    answers: ReviewAnswers;
    current: ReviewDecisionRecord | null;
    previous: ReviewDecisionRecord | null;
    canDecide: boolean;
    evidence: ReviewEvidence[];
    authorizations: ReviewAuthorization[];
};

export type DocumentItem = { value: string; label: string };

/** A kind of NOM-037 document, with the items of the norm it can cover (only the policy and the process have any). */
export type DocumentKindOption = {
    value: string;
    label: string;
    /** The numeral of the norm it answers to, when it has one. */
    numeral: string | null;
    items: DocumentItem[];
};

/** One version of a document in one work center. `missingItems` are the letters the version does not cover. */
export type DocumentRow = {
    id: string;
    kind: string;
    workCenter: { id: string; name: string };
    version: number;
    /** Y-m-d in the company's time zone. */
    publishedOn: string;
    coveredItems: string[];
    missingItems: string[];
};

export type DocumentFilters = {
    center: { value: string; label: string } | null;
    versions: 'latest' | 'all';
};
