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
