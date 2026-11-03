import { Form } from '@inertiajs/react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import { store } from '@/routes/tenant/seating/referrals';

/** Records a referral to medical attention, or «evaluated, no referral», with its date and a note without clinical detail. */
export default function ReferralForm({
    assessmentId,
}: {
    assessmentId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...store.form([tenantSlug, assessmentId])}
            options={{ preserveScroll: true }}
            resetOnSuccess
            noValidate
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <fieldset className="grid gap-2 sm:grid-cols-2">
                        <legend className="mb-1 font-medium">
                            Qué se hizo
                        </legend>
                        <label className={CHOICE_CLASS}>
                            <input
                                type="radio"
                                name="referred"
                                value="1"
                                className="size-5 accent-primary"
                            />
                            Se canalizó
                        </label>
                        <label className={CHOICE_CLASS}>
                            <input
                                type="radio"
                                name="referred"
                                value="0"
                                className="size-5 accent-primary"
                            />
                            Evaluada, sin canalización
                        </label>
                        <InputError message={errors['referred']} />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="referred-on">Fecha</Label>
                        <DatePicker
                            id="referred-on"
                            name="referred_on"
                            invalid={errors['referred_on'] !== undefined}
                        />
                        <InputError message={errors['referred_on']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="referral-note">
                            Nota (opcional, sin detalle clínico)
                        </Label>
                        <Textarea id="referral-note" name="note" rows={2} />
                        <InputError message={errors['note']} />
                    </div>
                    <Button
                        variant="outline"
                        className="w-fit"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Registrar
                    </Button>
                </>
            )}
        </Form>
    );
}
