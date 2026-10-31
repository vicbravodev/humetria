import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/committee/members';
import type { CommitteePerson } from '@/types';

/** Seats one more person of the company on the Comisión. */
export default function AssignMemberForm({
    tenantSlug,
    workCenterId,
    candidates,
}: {
    tenantSlug: string;
    workCenterId: string;
    candidates: CommitteePerson[];
}) {
    return (
        <Form
            {...store.form({ tenant: tenantSlug, workCenter: workCenterId })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-wrap items-end gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid min-w-64 flex-1 gap-2">
                        <Label htmlFor="assign-user">Agregar integrante</Label>
                        <Select
                            name="user_id"
                            disabled={candidates.length === 0}
                        >
                            <SelectTrigger
                                id="assign-user"
                                aria-invalid={
                                    errors['user_id'] === undefined
                                        ? undefined
                                        : true
                                }
                            >
                                <SelectValue
                                    placeholder={
                                        candidates.length === 0
                                            ? 'No hay más personas disponibles'
                                            : 'Elige a una persona'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {candidates.map((person) => (
                                    <SelectItem
                                        key={person.id}
                                        value={String(person.id)}
                                    >
                                        {person.name} · {person.role}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError role="alert" message={errors['user_id']} />
                    </div>
                    <Button disabled={processing || candidates.length === 0}>
                        {processing ? <Spinner /> : <UserPlus aria-hidden />}
                        Agregar
                    </Button>
                </>
            )}
        </Form>
    );
}
