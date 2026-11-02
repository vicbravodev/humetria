import { Form } from '@inertiajs/react';
import QuestionFields from '@/components/telework/question-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/telework/checklist/questions';
import type { ChecklistQuestion, RequirementOption } from '@/types';

/** Edits one question of the draft in place. Changing its text takes it off the DOF or Humetría text. */
export default function QuestionEditor({
    workCenterId,
    question,
    requirements,
    onDone,
}: {
    workCenterId: string;
    question: ChecklistQuestion;
    requirements: RequirementOption[];
    onDone: () => void;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <Form
            {...update.form({
                tenant: tenantSlug,
                workCenter: workCenterId,
                question: question.id,
            })}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-4 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    <QuestionFields
                        prefix={`edit-${question.id}`}
                        question={question}
                        requirements={requirements}
                        errors={errors}
                    />
                    <div className="flex gap-2 sm:col-span-2">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Guardar pregunta
                        </Button>
                        <Button type="button" variant="ghost" onClick={onDone}>
                            Cancelar
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
