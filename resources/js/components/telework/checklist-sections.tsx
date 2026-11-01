import { useState } from 'react';
import NewQuestionForm from '@/components/telework/new-question-form';
import QuestionEditor from '@/components/telework/question-editor';
import QuestionGroup from '@/components/telework/question-group';
import QuestionRow from '@/components/telework/question-row';
import { useChecklistGroups } from '@/hooks/use-checklist-groups';
import { sectionTitle } from '@/lib/telework/checklist';
import type { ChecklistQuestion, RequirementOption } from '@/types';

/** The checklist by section and heading, each question a row (pantallas: tablas con cabecera muted). */
export default function ChecklistSections({
    tenantSlug,
    workCenterId,
    questions,
    requirements,
    canEdit,
}: {
    tenantSlug: string;
    workCenterId: string;
    questions: ChecklistQuestion[];
    requirements: RequirementOption[];
    canEdit: boolean;
}) {
    const [editing, setEditing] = useState<string | null>(null);
    const labels = new Map(
        requirements.map((option) => [option.value, option.label]),
    );
    const labelOf = (question: ChecklistQuestion) =>
        question.requirement === null
            ? undefined
            : labels.get(question.requirement);
    const { groups, choose } = useChecklistGroups(questions);

    return (
        <div className="flex flex-col gap-8">
            {[1, 2].map((section) => (
                <section
                    key={section}
                    className="flex flex-col gap-4"
                    aria-labelledby={`section-${section}`}
                >
                    <h2
                        id={`section-${section}`}
                        className="text-xl font-semibold"
                    >
                        {sectionTitle(section)}
                    </h2>
                    {groups
                        .filter((group) => group.section === section)
                        .map((group) => (
                            <QuestionGroup
                                key={group.key}
                                heading={group.heading}
                                count={group.questions.length}
                                open={group.open}
                                onOpenChange={(open) =>
                                    choose(group.key, group.changed, open)
                                }
                            >
                                {group.questions.map((question) =>
                                    editing === question.id ? (
                                        <li
                                            key={question.id}
                                            className="px-5 py-4"
                                        >
                                            <QuestionEditor
                                                tenantSlug={tenantSlug}
                                                workCenterId={workCenterId}
                                                question={question}
                                                requirements={requirements}
                                                onDone={() => setEditing(null)}
                                            />
                                        </li>
                                    ) : (
                                        <QuestionRow
                                            key={question.id}
                                            tenantSlug={tenantSlug}
                                            workCenterId={workCenterId}
                                            question={question}
                                            requirementLabel={labelOf(question)}
                                            canEdit={canEdit}
                                            onEdit={() =>
                                                setEditing(question.id)
                                            }
                                        />
                                    ),
                                )}
                            </QuestionGroup>
                        ))}
                    {canEdit && (
                        <NewQuestionForm
                            tenantSlug={tenantSlug}
                            workCenterId={workCenterId}
                            section={section}
                            requirements={requirements}
                        />
                    )}
                </section>
            ))}
        </div>
    );
}
