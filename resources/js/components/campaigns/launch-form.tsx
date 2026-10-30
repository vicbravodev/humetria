import { Form } from '@inertiajs/react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { launch } from '@/routes/tenant/campaigns';

/** Launches the draft: everyone is invited by email and can answer until the end of the day chosen. */
export default function LaunchForm({
    tenantSlug,
    campaignId,
    min,
    suggested,
}: {
    tenantSlug: string;
    campaignId: string;
    min: string;
    suggested: string;
}) {
    return (
        <Form
            {...launch.form([tenantSlug, campaignId])}
            className="flex flex-col gap-4 rounded-xl border bg-card p-6"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="text-xl leading-7 font-semibold">
                        Lanzar la evaluación
                    </h2>
                    <p className="text-muted-foreground">
                        Cada persona recibe por correo su enlace personal y
                        puede contestar hasta el final del día que elijas.
                    </p>
                    {errors['campaign'] !== undefined && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {errors['campaign']}
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="grid max-w-sm gap-2">
                        <Label htmlFor="campaign-closes_on">
                            Último día para contestar
                        </Label>
                        <DatePicker
                            id="campaign-closes_on"
                            name="closes_on"
                            min={min}
                            defaultValue={suggested}
                            invalid={errors['closes_on'] !== undefined}
                            describedBy="campaign-closes_on-error"
                        />
                        <InputError
                            id="campaign-closes_on-error"
                            role="alert"
                            message={errors['closes_on']}
                        />
                    </div>
                    <Button
                        type="submit"
                        className="w-fit"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Lanzar e invitar
                    </Button>
                </>
            )}
        </Form>
    );
}
