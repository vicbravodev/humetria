import { Form } from '@inertiajs/react';
import PeopleChecklist from '@/components/campaigns/people-checklist';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, store } from '@/routes/tenant/campaigns/trauma';
import type { Paginated, RosterPerson } from '@/types';

/** Who of the work center answers Guía I, and until when. Sent at once by email. */
export default function TraumaForm({
    tenantSlug,
    workCenterId,
    people,
    search,
    minDeadline,
    suggestedDeadline,
}: {
    tenantSlug: string;
    workCenterId: string;
    people: Paginated<RosterPerson>;
    search: string;
    minDeadline: string;
    suggestedDeadline: string;
}) {
    return (
        <Form {...store.form(tenantSlug)} className="flex flex-col gap-6">
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="work_center_id"
                        value={workCenterId}
                    />
                    <InputError message={errors['work_center_id']} />
                    <PeopleChecklist
                        url={create.url(tenantSlug)}
                        workCenterId={workCenterId}
                        people={people}
                        search={search}
                        error={errors['employee_ids']}
                    />
                    <div className="grid max-w-sm gap-2">
                        <Label htmlFor="trauma-closes_on">
                            Último día para contestar
                        </Label>
                        <DatePicker
                            id="trauma-closes_on"
                            name="closes_on"
                            min={minDeadline}
                            defaultValue={suggestedDeadline}
                            invalid={errors['closes_on'] !== undefined}
                            describedBy="trauma-closes_on-error"
                        />
                        <InputError
                            id="trauma-closes_on-error"
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
                        Enviar Guía I
                    </Button>
                </>
            )}
        </Form>
    );
}
