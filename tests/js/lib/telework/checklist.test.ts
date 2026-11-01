import { describe, expect, it } from 'vitest';
import {
    favorableLabel,
    groupBySection,
    groupIsOpen,
    originLabel,
    originVariant,
    sectionTitle,
    startsOpen,
} from '@/lib/telework/checklist';
import type { QuestionGroup } from '@/lib/telework/checklist';
import type { ChecklistQuestion } from '@/types';

function question(
    code: string,
    section: number,
    heading: string,
): ChecklistQuestion {
    return {
        id: code,
        section,
        code,
        heading,
        text: code,
        origin: 'dof',
        favorable: 'yes',
        requirement: null,
        sensitive: false,
    };
}

describe('originLabel', () => {
    it('names where each question comes from', () => {
        expect(originLabel('dof')).toBe('Apéndice 3');
        expect(originLabel('dof_modified')).toBe('Apéndice 3 modificada');
        expect(originLabel('humetria')).toBe('Humetría');
        expect(originLabel('committee')).toBe('Comisión');
    });

    it('marks the questions that no longer read as the DOF with a warning', () => {
        expect(originVariant('dof')).toBe('secondary');
        expect(originVariant('humetria')).toBe('outline');
        expect(originVariant('dof_modified')).toBe('warning');
        expect(originVariant('committee')).toBe('default');
    });
});

describe('favorableLabel', () => {
    it('says which answer flags no risk', () => {
        expect(favorableLabel('yes')).toBe('Favorable: Sí');
        expect(favorableLabel('no')).toBe('Favorable: No');
        expect(favorableLabel('informative')).toBe('Solo informativa');
    });
});

describe('sectionTitle', () => {
    it('titles the two sections', () => {
        expect(sectionTitle(1)).toBe('Sección I: lugar propuesto');
        expect(sectionTitle(2)).toBe('Sección II: revisión periódica');
    });
});

describe('groupBySection', () => {
    it('groups consecutive questions by section and heading, keeping their order', () => {
        const groups = groupBySection([
            question('a', 1, 'Luz'),
            question('b', 1, 'Luz'),
            question('c', 1, 'Ruido'),
            question('d', 2, 'Luz'),
        ]);

        expect(
            groups.map((group) => [
                group.section,
                group.heading,
                group.questions.map((item) => item.code),
            ]),
        ).toEqual([
            [1, 'Luz', ['a', 'b']],
            [1, 'Ruido', ['c']],
            [2, 'Luz', ['d']],
        ]);
    });

    it('is empty without questions', () => {
        expect(groupBySection([])).toEqual([]);
    });
});

describe('startsOpen', () => {
    const group = (...questions: ChecklistQuestion[]) => ({
        section: 1,
        heading: 'Luz',
        questions,
    });

    it('opens the first group, to start editing right away', () => {
        expect(startsOpen(group(question('a', 1, 'Luz')), 0)).toBe(true);
        expect(startsOpen(group(question('a', 1, 'Luz')), 1)).toBe(false);
    });

    it('opens a group the Comisión has changed, so its own questions show', () => {
        const base = question('a', 1, 'Luz');

        expect(
            startsOpen(group(base, { ...base, origin: 'committee' }), 3),
        ).toBe(true);
        expect(startsOpen(group({ ...base, origin: 'dof_modified' }), 3)).toBe(
            true,
        );
        expect(startsOpen(group({ ...base, origin: 'humetria' }), 3)).toBe(
            false,
        );
    });
});

describe('groupIsOpen', () => {
    const base = question('a', 1, 'Luz');
    const plain: QuestionGroup = {
        section: 1,
        heading: 'Luz',
        questions: [base],
    };
    const changed: QuestionGroup = {
        ...plain,
        questions: [base, { ...base, id: 'c', origin: 'committee' }],
    };

    it('starts as startsOpen says until someone chooses', () => {
        expect(groupIsOpen(undefined, plain, 0)).toBe(true);
        expect(groupIsOpen(undefined, plain, 2)).toBe(false);
    });

    it('keeps what was chosen while the group gains nothing new', () => {
        expect(groupIsOpen({ open: false, changed: 0 }, plain, 0)).toBe(false);
        expect(groupIsOpen({ open: true, changed: 0 }, plain, 2)).toBe(true);
        expect(groupIsOpen({ open: false, changed: 1 }, changed, 2)).toBe(
            false,
        );
        expect(groupIsOpen({ open: false, changed: 2 }, changed, 2)).toBe(
            false,
        );
    });

    it('opens a closed group once the Comisión adds or modifies a question in it', () => {
        expect(groupIsOpen({ open: false, changed: 0 }, changed, 2)).toBe(true);
    });
});
