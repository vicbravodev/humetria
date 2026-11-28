import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import DemoOwnerField from '@/components/demos/demo-owner-field';
import DemoStatusField from '@/components/demos/demo-status-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/demo-requests';
import type { DemoRequestRow } from '@/types';

/** Where a request stands, who of the staff owns it, its next step and the internal note, saved together. */
export default function DemoRequestForm({
    request,
}: {
    request: DemoRequestRow;
}) {
    return (
        <Form
            {...update.form(request.id)}
            options={{ preserveScroll: true }}
            key={`${request.id}-${request.status}-${request.note}-${request.owner?.id}-${request.next_step}`}
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <DemoStatusField
                            request={request}
                            error={errors['status']}
                        />
                        <DemoOwnerField
                            request={request}
                            error={errors['owner_user_id']}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`next-step-${request.id}`}>
                            Siguiente paso
                        </Label>
                        <Input
                            id={`next-step-${request.id}`}
                            name="next_step"
                            maxLength={200}
                            defaultValue={request.next_step ?? ''}
                            placeholder="Mandar la propuesta el lunes"
                        />
                        <InputError message={errors['next_step']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`note-${request.id}`}>
                            Nota interna
                        </Label>
                        <Textarea
                            id={`note-${request.id}`}
                            name="note"
                            maxLength={2000}
                            defaultValue={request.note ?? ''}
                            placeholder="Qué se habló, qué le interesa, qué objeciones tiene…"
                        />
                        <InputError message={errors['note']} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        className="w-fit"
                        disabled={processing}
                    >
                        {processing ? <Spinner /> : <Save aria-hidden />}
                        Guardar
                    </Button>
                </>
            )}
        </Form>
    );
}
