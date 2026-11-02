import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import QuestionFields from '@/components/telework/question-fields';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/telework/checklist/questions';
import type { RequirementOption } from '@/types';

/** Adds a question of the Comisión at the end of a section. */
export default function NewQuestionForm({
    workCenterId,
    section,
    requirements,
}: {
    workCenterId: string;
    section: number;
    requirements: RequirementOption[];
}) {
    const tenantSlug = useTenantSlug();
    return (
        <Form
            {...store.form({ tenant: tenantSlug, workCenter: workCenterId })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid gap-4 card p-5 sm:grid-cols-2"
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
                        <InputError message={errors['heading']} />
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
