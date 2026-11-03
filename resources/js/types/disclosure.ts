import type { Paginated } from './demos';
import type { Choice } from './interventions';

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
