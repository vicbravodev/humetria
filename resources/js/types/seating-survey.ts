/** The latest confidential survey of a center: where it stands and how many people it reached. Never who answered. */
export type SurveyRecord = {
    id: string;
    status: 'draft' | 'active' | 'closed';
    statusLabel: string;
    closesOn: string | null;
    participants: number;
    answered: number;
};

/** One survey question counted; under the minimum of people it carries no count, not even how many answered it. */
export type SurveyQuestion = {
    number: number;
    code: string;
    text: string;
} & (
    | { visible: false }
    | { visible: true; yes: number; no: number; notApplicable: number }
);

export type SurveyResults = {
    answered: number;
    visible: boolean;
    questions: SurveyQuestion[];
    areas: {
        visible: { id: string; name: string; answered: number }[];
        hidden: number;
    };
    area: { value: string; label: string } | null;
};

/** A part of a work center the person may open from its board; `count` is how many records it holds, none for a form. */
export type SeatingCenterTab = {
    key: string;
    label: string;
    count: number | null;
};

/** One of the thirteen requirements of a center: where it stands and what is behind it (counts, never a name). */
export type CenterRequirement = {
    key: string;
    number: string;
    label: string;
    /** The tab where it is put right; none when it lives outside Ley Silla. */
    tab: string | null;
    state: 'met' | 'warning' | 'missing' | 'pending' | 'not_applicable';
    stateLabel: string;
    count: number;
    /** Since when the oldest thing behind it has been waiting, Y-m-d. */
    since: string | null;
};

export type RequirementsSummary = {
    met: number;
    warning: number;
    missing: number;
    /** Nothing registered yet to judge them by: «Sin registros», never counted as met. */
    pending: number;
    /** No position needs them: «No aplica», left out of the count. */
    notApplicable: number;
};
