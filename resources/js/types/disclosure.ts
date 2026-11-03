import type { Paginated } from './demos';
import type { Choice } from './interventions';
import type { RiskLevelValue } from './results';

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

export type BoardCenter = {
    id: string;
    name: string;
    state: Choice<'met' | 'notice' | 'missing'>;
    requirements: BoardRequirement[];
};

/** How many work centers are in each overall state. */
export type BoardSummary = { met: number; notice: number; missing: number };

export type DisclosureIndexProps = {
    summary: BoardSummary;
    centers: Paginated<BoardCenter>;
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

/** A work center as the public page lists it: its name, nothing else. */
export type PublicCenter = { id: string; name: string };

export type PublicCentersProps = {
    token: string;
    company: string;
    centers: Paginated<PublicCenter>;
    filters: { q: string };
};

export type PublicCenterProps = {
    token: string;
    company: string;
    center: PublicCenter;
    /** Whether the company has another center to choose from. */
    others: boolean;
    /** What the company published for b, c, d and f in the center, by inciso; null while there is nothing. */
    blocks: Record<string, PublicBlockText | null>;
    /** The measures of the Programa RH chose to show, by inciso (b and c): the whole center, never people or areas. */
    measures: Record<string, PublicMeasure[]>;
    /** The channel for complaints as the channel says it; null while it takes nothing. */
    channel: PublicChannel | null;
    /** The results block (5.7 e); null where 7.8 does not apply, a center of up to 15 people. */
    results: PublicResults | null;
    /** The policy in force in the center; null while it has none. */
    policy: {
        version: number;
        publishedOn: string;
        sections: PolicyTextSection[];
    } | null;
};

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

/** What the company published for a block of 5.7 in a center: the words and the day. */
export type PublicBlockText = { text: string; publishedOn: string };

/** A category or a domain in the results of a center: how many questionnaires fall in each level. */
export type PublicRiskGroup = {
    key: string;
    label: string;
    levels: number[];
};

/** The results the company published for a center, of the whole center and with the minimum applied. */
export type PublicResults = {
    title: string;
    published:
        | { state: 'below_minimum'; closedOn: string }
        | {
              state: 'available';
              closedOn: string;
              guide: 'ii' | 'iii';
              answered: number;
              levels: { value: RiskLevelValue; label: string }[];
              final: number[];
              categories: PublicRiskGroup[];
              domains: PublicRiskGroup[];
          }
        | null;
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

/** A measure of the Programa as the public page shows it: what, which level, where it stands and by when. */
export type PublicMeasure = {
    title: string;
    level: number;
    status: string;
    statusLabel: string;
    dueOn: string;
};

/** The channel for complaints and reports: where to go, whether it takes anonymous ones and what it takes. */
export type PublicChannel = {
    url: string;
    anonymousAllowed: boolean;
    categories: string[];
};
