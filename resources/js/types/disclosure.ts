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

export type PolicyDraft = {
    id: string;
    sections: PolicySectionData[];
};

export type PolicyPageProps = {
    draft: PolicyDraft | null;
    /** The note of Guía IV: help for whoever writes, not part of the policy. */
    note: string;
    can: { manage: boolean };
};
