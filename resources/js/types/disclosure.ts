import type { Paginated } from './demos';
import type { Choice } from './interventions';
import type {
    FlowAnswers,
    FlowQuestion,
    FlowSection,
} from '@/lib/question-flow';

/** A section of a policy as the page edits and shows it: plain text, a list of items and how they are marked. */
export type PolicySectionData = {
    key: string;
    heading: string;
    text: string;
    items: string[];
    listStyle: 'numbers' | 'letters' | null;
    removable: boolean;
    required: boolean;
};

/** A section of a published policy as people read it: the company name already in its place. */
export type PolicyTextSection = Pick<
    PolicySectionData,
    'key' | 'heading' | 'text' | 'items' | 'listStyle'
>;

/** A published version in the history: counts and days, no words. */
export type PolicyVersionRow = {
    id: string;
    version: number;
    publishedOn: string | null;
    /** The incisos of 5.1 it declares. */
    covers: string[];
    /** In how many work centers it was published. */
    centers: number;
};

export type PolicyVersion = PolicyVersionRow & {
    sections: PolicyTextSection[];
};

export type PolicyDraft = {
    id: string;
    sections: PolicySectionData[];
};

export type PolicyPageProps = {
    draft: PolicyDraft | null;
    /** The version published last, with its words. */
    latest: PolicyVersion | null;
    history: Paginated<PolicyVersionRow>;
    /** How many work centers are in use: «todos los centros» in the dialog that publishes. */
    centersInUse: number;
    /** The note of Guía IV: help for whoever writes, not part of the policy. */
    note: string;
    can: { manage: boolean };
};

export type RequirementValue = 'met' | 'notice' | 'missing' | 'not_applicable';

/** One line of the board for a work center: what the norm asks, where it stands and why in words. */
export type BoardRequirement = {
    code: string;
    numeral: string;
    label: string;
    state: Choice<RequirementValue>;
    detail: string | null;
};

/** Where one of the six things of 5.7 stands in a center (board V4-Difusion). */
export type MatrixCell =
    | 'done'
    | 'missing'
    | 'undelivered'
    | 'ready'
    | 'waiting'
    | 'not_applicable';

export type BoardCenter = {
    id: string;
    name: string;
    state: Choice<'met' | 'notice' | 'missing'>;
    requirements: BoardRequirement[];
    /** By thing of 5.7: a, b, c, d, e and f. */
    cells: Record<string, MatrixCell>;
};

/** A row of the matrix: one of the six things the people must know, with its way in for whoever manages. */
export type MatrixItem = {
    key: string;
    numeral: string;
    label: string;
    caption: string;
    /** Its writing started and was left. */
    draft: boolean;
    action: { label: string; href: string; primary: boolean } | null;
};

/** How many work centers are in each overall state. */
export type BoardSummary = { met: number; notice: number; missing: number };

export type DisclosureIndexProps = {
    summary: BoardSummary;
    /** The columns of the matrix, a page at a time. */
    centers: Paginated<BoardCenter>;
    /** The six rows, a fixed catalog. */
    items: MatrixItem[];
    progress: { ready: number; total: number; minutes: number };
    next: {
        item: string;
        title: string;
        text: string;
        action: { label: string; href: string };
    } | null;
    reach: { page: boolean; confirmed: number; people: number };
    can: { manage: boolean };
};

/** Where a person's own confirmation of the policy stands. */
export type PolicyAcknowledgment = {
    documentId: string;
    state: 'pending' | 'confirmed';
    on: string | null;
};

export type PortalPolicyProps = {
    token: string;
    company: string;
    name: string;
    /** The version in force in the person's center; null while the center has none. */
    policy: {
        version: number;
        publishedOn: string;
        sections: PolicyTextSection[];
    } | null;
    acknowledgment: PolicyAcknowledgment | null;
};

/** How far the confirmations of a version are, over the people active today in its centers. */
export type PolicyAckProgress = {
    active: number;
    confirmed: number;
    pending: number;
    percent: number;
};

export type PolicyAckStatus = 'all' | 'pending' | 'confirmed';

export type PolicyAckFilters = { status: PolicyAckStatus; q: string };

/** A person of the follow-up list: what is recorded of their confirmation. No email, no link. */
export type PolicyAckPerson = {
    id: string;
    name: string;
    jobTitle: string;
    state: 'pending' | 'confirmed';
    channelLabel: string | null;
    on: string | null;
    hasEmail: boolean;
};

export type VersionSummary = {
    id: string;
    version: number;
    publishedOn: string | null;
};

export type DisseminationProps = {
    version: (VersionSummary & { centers: number }) | null;
    versions: VersionSummary[];
    progress: PolicyAckProgress | null;
    counts: Record<PolicyAckStatus, number> | null;
    filters: PolicyAckFilters;
    people: Paginated<PolicyAckPerson>;
    /** Today and the day of publication, as Y-m-d, to bound the day of a paper confirmation. */
    today: string;
    publishedDay: string | null;
    records: Paginated<DisseminationRecordRow>;
};

/** A physical or in-person means RH recorded as evidence of the disclosure. */
export type DisseminationRecordRow = {
    id: string;
    workCenter: string;
    mediumLabel: string;
    /** The incisos of 5.7 (a to f) it covered. */
    items: string[];
    doneOn: string;
    note: string | null;
};

/** The permanent link to the page of the workers, as the management screen shows it. */
export type PageLinkData = {
    state: Choice<'active' | 'disabled' | 'expired'>;
    /** What RH prints; null when the link can no longer be derived (its app key was dropped) and has to be replaced. */
    url: string | null;
    needsRotation: boolean;
    version: number;
    createdOn: string;
    rotatedOn: string | null;
    expiresOn: string | null;
    /** The last day it opens, as Y-m-d. */
    expiresDay: string | null;
};

export type PageLinkProps = { link: PageLinkData | null };

/** A block of 5.7 the company writes by hand (b, c, d or f): the text in force and the draft being written. */
export type ContentBlock = {
    kind: string;
    inciso: string;
    label: string;
    /** How to write it: what the norm asks and what to avoid. */
    note: string;
    latest: {
        id: string;
        version: number;
        publishedOn: string | null;
        text: string;
        centers: number;
    } | null;
    draft: { id: string; text: string } | null;
};

export type ContentsProps = {
    blocks: ContentBlock[];
    centersInUse: number;
};

/** A closed evaluation on the screen that publishes results to the people. */
export type ResultsRow = {
    id: string;
    workCenter: string;
    closedOn: string | null;
    guide: string;
    state: 'published' | 'unpublished';
    publishedOn: string | null;
    canPublish: boolean;
};

export type ResultsProps = { evaluations: Paginated<ResultsRow> };

/** «Comparte los resultados con tu equipo» (board V2-N035-Compartir): what each person of the center will read. */
export type ShareResultsProps = {
    campaign: { id: string; closedOn: string | null };
    workCenter: { id: string; name: string };
    company: string;
    /** Null below the minimum: the page says «menos de N», never the count. */
    answered: number | null;
    minimum: number;
    themes: string[];
    measures: { title: string; dueOn: string }[];
    /** The complaints channel, when it is open. */
    channel: { anonymous: boolean } | null;
    /** Whether the center has a policy in force. */
    policy: boolean;
    /** The day it was published, while it is. */
    published: string | null;
    showMeasures: boolean;
    canPublish: boolean;
};

/** The policy or a block of 5.7 written with questions (boards V2-N035-Politica and V4-Difusion-Contenidos; decisión A3). */
export type DisclosureWritingProps = {
    document: {
        kind: string;
        /** The inciso of 5.7 it is about: «a» for the policy. */
        inciso: string;
        title: string;
        caption: string;
        owner: string;
        company: string;
    };
    questions: FlowQuestion[];
    answers: FlowAnswers;
    /** The question it was on; the number of questions is the review. */
    step: number;
    sections: FlowSection[];
    minutes: number;
    publication: {
        draftId: string | null;
        centersInUse: number;
        version: { number: number; publishedOn: string } | null;
    };
    /** Where «Guardar y volver» leads, and the center whose route it is. */
    back: { href: string; label: string; center: string | null };
};
