import type { RowStateValue } from './telework-center';
import type { WorkplaceStatusValue } from './telework';

export type DossierHeader = {
    legalName: string;
    rfc: string;
    workCenter: string;
    address: string;
    /** Y-m-d, in the company's time zone. */
    generatedOn: string;
    generatedBy: string;
};

export type DossierRow = {
    numeral: string;
    label: string;
    state: RowStateValue;
    stateLabel: string;
    detail: string | null;
};

/** The active people of the roster; the three personal fields come only for whoever manages NOM-037. */
export type DossierPerson = {
    name: string;
    jobTitle: string;
    percentage: number;
    startedOn: string | null;
    maritalStatus?: string | null;
    contactPhone?: string | null;
    homeAddress?: string | null;
};

export type DossierWorkplace = {
    person: string;
    status: WorkplaceStatusValue;
    statusLabel: string;
    review: {
        decidedOn: string;
        decision: string;
        method: string;
        validUntil: string | null;
    } | null;
};

export type DossierChecklist = {
    version: number;
    publishedOn: string | null;
    gaps: { section: number; requirements: string[] }[];
};

export type DossierDocument = {
    numeral: string | null;
    kind: string;
    version: number;
    publishedOn: string;
    coveredItems: string[];
    missingItems: string[];
    acknowledgements: { acknowledged: number; expected: number } | null;
    href: string;
};

export type DossierEquipment = {
    person: string;
    received: number;
    refused: number;
    pending: number;
    lastDeliveredOn: string | null;
    justified: boolean;
};

export type DossierTraining = {
    heldOn: string;
    modality: string;
    topics: string[];
    attendees: number;
};

export type DossierOverdue = { person: string; dueOn: string };

export type DossierNotices = {
    byKind: { label: string; count: number }[];
    reviewed: number;
    pending: number;
};

export type DossierCommittee = {
    constitutedOn: string;
    members: string[];
    actHref: string | null;
};

/** Photos and answers are only counted: the dossier may leave the system. */
export type DossierEvidence = {
    checklists: number;
    photos: number;
    href: string;
};

/** Every block of evidence of the dossier, by the data it reads. */
export type DossierData = {
    roster: DossierPerson[];
    workplaces: DossierWorkplace[];
    checklist: DossierChecklist | null;
    documents: DossierDocument[];
    equipment: DossierEquipment[];
    trainings: DossierTraining[];
    overdue: DossierOverdue[];
    notices: DossierNotices;
    committee: DossierCommittee | null;
    evidence: DossierEvidence;
};
