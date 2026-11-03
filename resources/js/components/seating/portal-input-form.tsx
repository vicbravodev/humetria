import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import PortalChoiceGroup from '@/components/seating/portal-choice-group';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import { store } from '@/routes/portal/seating/inputs';
import type { PortalCondition } from '@/types';

/** What the person answers: footwear, discomfort, a note and the acceptance of the notice. Voluntary, and said so. */
export default function PortalInputForm({
    token,
    notice,
    footwear,
    discomfort,
}: {
    token: string;
    notice: string;
    footwear: PortalCondition;
    discomfort: PortalCondition;
}) {
    return (
        <Form
            {...store.form({ token })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            noValidate
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <p className="card p-4 text-base text-muted-foreground">
                        {notice}
                    </p>
                    <PortalChoiceGroup
                        name="footwear"
                        condition={footwear}
                        error={errors['footwear']}
                    />
                    <PortalChoiceGroup
                        name="discomfort"
                        condition={discomfort}
                        error={errors['discomfort']}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="portal-note">
                            ¿Algo más que quiera contarnos? (opcional)
                        </Label>
                        <Textarea id="portal-note" name="note" rows={3} />
                        <InputError message={errors['note']} />
                    </div>
                    <div className="grid gap-2">
                        <label className={CHOICE_CLASS}>
                            <input
                                type="checkbox"
                                name="accepted"
                                value="1"
                                className="size-5 shrink-0 accent-primary"
                            />
                            He leído el aviso y acepto contestar
                        </label>
                        <InputError message={errors['accepted']} />
                    </div>
                    <Button size="lg" disabled={processing}>
                        {processing && <Spinner />}
                        Enviar mis respuestas
                    </Button>
                </>
            )}
        </Form>
    );
}
