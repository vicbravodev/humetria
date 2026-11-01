import { Form, Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import type { ReactNode } from 'react';
import PeopleChecklist from '@/components/campaigns/people-checklist';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useChosenPeople } from '@/hooks/use-chosen-people';
import { index } from '@/routes/tenant/campaigns';
import { create, store } from '@/routes/tenant/campaigns/trauma';
import type { ChoiceOption, Paginated, RosterPerson } from '@/types';

/**
 * Who of the work center answers Guía I, and until when (pantalla Guia-I-Acontecimiento). Sent at once by email.
 * `picker` is the work center field, which changes the page rather than the form.
 */
export default function TraumaForm({
    tenantSlug,
    picker,
    workCenter,
    people,
    preselected,
    search,
    maxPeople,
    deadline,
}: {
    tenantSlug: string;
    picker: ReactNode;
    workCenter: ChoiceOption;
    people: Paginated<RosterPerson>;
    /** Who a traumatic-event notice named: it starts ticked. */
    preselected: { id: string; name: string } | null;
    search: string;
    maxPeople: number;
    deadline: { min: string; suggested: string };
}) {
    const choice = useChosenPeople(workCenter.value, preselected);
    const count = choice.chosen.length;

    return (
        <Form {...store.form(tenantSlug)} className="flex flex-col gap-5">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(16.5rem,1fr)]">
                        {picker}
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="trauma-closes_on">
                                Último día para contestar
                            </Label>
                            <DatePicker
                                id="trauma-closes_on"
                                name="closes_on"
                                min={deadline.min}
                                defaultValue={deadline.suggested}
                                invalid={errors['closes_on'] !== undefined}
                                describedBy="trauma-closes_on-error"
                            />
                            <InputError
                                id="trauma-closes_on-error"
                                role="alert"
                                message={errors['closes_on']}
                            />
                        </div>
                    </div>
                    <input
                        type="hidden"
                        name="work_center_id"
                        value={workCenter.value}
                    />
                    <InputError message={errors['work_center_id']} />
                    <PeopleChecklist
                        url={create.url(tenantSlug)}
                        workCenter={workCenter}
                        people={people}
                        search={search}
                        maxPeople={maxPeople}
                        choice={choice}
                        error={errors['employee_ids']}
                    />
                    <div className="flex flex-wrap justify-end gap-2.5 border-t pt-[18px]">
                        <Button variant="ghost" asChild>
                            <Link href={index(tenantSlug)}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Send aria-hidden />}
                            Enviar Guía I
                            {count > 0 &&
                                ` a ${count} ${count === 1 ? 'persona' : 'personas'}`}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
