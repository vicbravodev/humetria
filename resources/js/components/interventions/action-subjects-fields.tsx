import ChoiceField from '@/components/humetria/choice-field';
import AreaChecklist from '@/components/interventions/area-checklist';
import type { AreaOption } from '@/types';

type Errors = Partial<Record<string, string>>;
type Subjects = 'center' | 'areas';

const OPTIONS: { value: Subjects; label: string }[] = [
    { value: 'center', label: 'Todo el centro de trabajo' },
    { value: 'areas', label: 'Algunas áreas' },
];

/**
 * Who the action is for (num. 8.4 a; criterio C-33): the whole work center or areas, never people. Choosing people would tie
 * them to a result of the Guía II or III.
 */
export default function ActionSubjectsFields({
    areas,
    itemId,
    selected,
    subjects,
    onSubjects,
    errors,
}: {
    areas: AreaOption[];
    itemId: string;
    /** The areas the action already has. */
    selected: string[];
    subjects: Subjects;
    onSubjects: (subjects: Subjects) => void;
    errors: Errors;
}) {
    return (
        <>
            <ChoiceField
                name="subjects"
                legend="A quién va dirigida"
                options={OPTIONS}
                value={subjects}
                onChange={onSubjects}
                error={errors['subjects']}
                columns={2}
            />
            {subjects === 'areas' && (
                <AreaChecklist
                    areas={areas}
                    itemId={itemId}
                    selected={selected}
                    error={errors['area_ids']}
                />
            )}
        </>
    );
}
