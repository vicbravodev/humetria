import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { launch } from '@/routes/tenant/seating/survey';

/** Launches the survey for everyone in the center: the only thing HR decides is the last day people can answer. */
export default function SurveyLaunchForm({
    workCenterId,
}: {
    workCenterId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...launch.form([tenantSlug, workCenterId])}
            noValidate
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2 sm:w-72">
                        <Label htmlFor="survey-closes-on">
                            Último día para contestar
                        </Label>
                        <DatePicker
                            id="survey-closes-on"
                            name="closes_on"
                            invalid={errors['closes_on'] !== undefined}
                        />
                        <InputError message={errors['closes_on']} />
                    </div>
                    <Button className="w-fit" disabled={processing}>
                        {processing ? <Spinner /> : <Send aria-hidden />}
                        Lanzar la encuesta
                    </Button>
                </>
            )}
        </Form>
    );
}
