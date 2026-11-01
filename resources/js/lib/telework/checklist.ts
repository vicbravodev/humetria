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

/** How many questions of a group the Comisión has added or reworded. */
export function changedCount(group: QuestionGroup): number {
    return group.questions.filter(
        (question) =>
            question.origin === 'committee' ||
            question.origin === 'dof_modified',
    ).length;
}

/** A group shows its questions from the start when it is the first one, or the Comisión has changed it; the rest open on demand. */
export function startsOpen(group: QuestionGroup, index: number): boolean {
    return index === 0 || changedCount(group) > 0;
}

/** Someone opened or closed a group, when it had `changed` questions of the Comisión. */
export type GroupChoice = { open: boolean; changed: number };

/** What was chosen holds until the Comisión adds or rewords a question in the group: then it shows open, so the change is seen. */
export function groupIsOpen(
    choice: GroupChoice | undefined,
    group: QuestionGroup,
    index: number,
): boolean {
    return choice !== undefined && choice.changed >= changedCount(group)
        ? choice.open
        : startsOpen(group, index);
}
