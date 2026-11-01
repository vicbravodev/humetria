import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import QuestionFields from '@/components/telework/question-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/tenant/telework/checklist/questions';
import type { RequirementOption } from '@/types';

/** Adds a question of the Comisión at the end of a section. */
export default function NewQuestionForm({
    tenantSlug,
    workCenterId,
    section,
    requirements,
}: {
    tenantSlug: string;
    workCenterId: string;
    section: number;
    requirements: RequirementOption[];
}) {
    return (
        <Form
            {...store.form({ tenant: tenantSlug, workCenter: workCenterId })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="section" value={section} />
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor={`new-${section}-heading`}>Tema</Label>
                        <Input
                            id={`new-${section}-heading`}
                            name="heading"
                            placeholder="Iluminación, Ruido, Sillas…"
                            required
                        />
                        <InputError role="alert" message={errors['heading']} />
                    </div>
                    <QuestionFields
                        prefix={`new-${section}`}
                        requirements={requirements}
                        errors={errors}
                    />
                    <div className="sm:col-span-2">
                        <Button
                            disabled={processing}
                            data-test={`add-question-${section}`}
                        >
                            <Plus aria-hidden />
                            Agregar pregunta
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
