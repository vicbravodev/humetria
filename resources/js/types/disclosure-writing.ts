import type {
    FlowAnswers,
    FlowQuestion,
    FlowSection,
} from '@/lib/question-flow';

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
