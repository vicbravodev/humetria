import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import AccidentAnswered from '@/components/telework/accident-answered';
import AccidentActionChips from '@/components/telework/accident-action-chips';
import AccidentStep from '@/components/telework/accident-step';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { review } from '@/routes/tenant/telework/notices';
import type { AccidentNotice, Choice } from '@/types';

const NOTE_HINT =
    'Por ejemplo: se le entregó una faja y se acordó no cargar más de 10 kg.';

/**
 * Steps 1 and 2 of an accident (board V3-Aviso): what was done, without clinical detail, and the button that saves it and
 * sends it to the person's portal. Once saved, what was recorded, read only.
 */
export default function AccidentAnswer({
    notice,
    choices,
}: {
    notice: AccidentNotice;
    choices: Choice[];
}) {
    const tenantSlug = useTenantSlug();
    const [chosen, setChosen] = useState<string[]>([]);
    const answered = notice.reviewedOn !== null;

    if (answered) {
        return <AccidentAnswered notice={notice} choices={choices} />;
    }

    return (
        <Form
            {...review.form({ tenant: tenantSlug, notice: notice.id })}
            options={{ preserveScroll: true }}
            className="grid"
        >
            {({ processing, errors }) => (
                <>
                    <AccidentStep
                        number={1}
                        state="now"
                        title="Registra qué se hizo"
                        text="Elige todo lo que aplique. Sin detalle médico: eso se queda en su expediente clínico."
                    >
                        <AccidentActionChips
                            choices={choices}
                            chosen={chosen}
                            onChange={setChosen}
                        />
                        <InputError
                            message={
                                errors['actions'] ??
                                errors['actions.0'] ??
                                errors['notice']
                            }
                        />
                        <div className="grid gap-2">
                            <label
                                htmlFor="accident-note"
                                className="text-sm font-medium"
                            >
                                Nota (opcional)
                            </label>
                            <Textarea
                                id="accident-note"
                                name="note"
                                rows={2}
                                maxLength={2000}
                                placeholder={NOTE_HINT}
                            />
                            <InputError message={errors['note']} />
                        </div>
                    </AccidentStep>
                    <AccidentStep
                        number={2}
                        state="next"
                        title={`Avísale a ${notice.person} que lo leíste`}
                        text="Le llega a su portal lo que registraste. Así sabe que su aviso no se perdió."
                    >
                        <Button
                            type="submit"
                            disabled={processing || chosen.length === 0}
                            className="self-start"
                        >
                            {processing && <Spinner />}
                            Guardar y avisar a {notice.person}
                        </Button>
                    </AccidentStep>
                </>
            )}
        </Form>
    );
}
