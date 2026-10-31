import type {
    ChecklistQuestion,
    FavorableAnswer,
    QuestionOrigin,
} from '@/types';

const ORIGINS: Record<QuestionOrigin, string> = {
    dof: 'Apéndice 3',
    dof_modified: 'Apéndice 3 modificada',
    humetria: 'Humetría',
    committee: 'Comisión',
};

const VARIANTS: Record<
    QuestionOrigin,
    'secondary' | 'outline' | 'warning' | 'default'
> = {
    dof: 'secondary',
    humetria: 'outline',
    dof_modified: 'warning',
    committee: 'default',
};

export function originLabel(origin: QuestionOrigin): string {
    return ORIGINS[origin];
}

export function originVariant(origin: QuestionOrigin) {
    return VARIANTS[origin];
}

const FAVORABLE: Record<FavorableAnswer, string> = {
    yes: 'Favorable: Sí',
    no: 'Favorable: No',
    informative: 'Solo informativa',
};

export function favorableLabel(favorable: FavorableAnswer): string {
    return FAVORABLE[favorable];
}

export function sectionTitle(section: number): string {
    return section === 1
        ? 'Sección I: lugar propuesto'
        : 'Sección II: revisión periódica';
}

export type QuestionGroup = {
    section: number;
    heading: string;
    questions: ChecklistQuestion[];
};

/** Consecutive questions of one section and heading, in the order the server sent them. */
export function groupBySection(
    questions: ChecklistQuestion[],
): QuestionGroup[] {
    return questions.reduce<QuestionGroup[]>((groups, question) => {
        const last = groups.at(-1);

        if (
            last !== undefined &&
            last.section === question.section &&
            last.heading === question.heading
        ) {
            last.questions.push(question);

            return groups;
        }

        return [
            ...groups,
            {
                section: question.section,
                heading: question.heading,
                questions: [question],
            },
        ];
    }, []);
}
