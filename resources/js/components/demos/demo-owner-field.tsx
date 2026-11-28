import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DemoOwner, DemoRequestRow } from '@/types';

/** A select item cannot carry an empty value: this one stands for nobody and is sent as nothing. */
const NOBODY = 'nobody';

/**
 * Who of the staff owns the request. The staff arrives after the page shows; until then only the current owner is
 * offered, so saving early keeps it.
 */
export default function DemoOwnerField({
    request,
    error,
}: {
    request: DemoRequestRow;
    error: string | undefined;
}) {
    const { staff } = usePage<{ staff?: DemoOwner[] }>().props;
    const [owner, setOwner] = useState(
        request.owner === null ? NOBODY : String(request.owner.id),
    );
    const options = staff ?? (request.owner === null ? [] : [request.owner]);

    return (
        <div className="grid gap-2">
            <Label htmlFor={`owner-${request.id}`}>Responsable</Label>
            <input
                type="hidden"
                name="owner_user_id"
                value={owner === NOBODY ? '' : owner}
            />
            <Select value={owner} onValueChange={setOwner}>
                <SelectTrigger id={`owner-${request.id}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NOBODY}>Sin asignar</SelectItem>
                    {options.map((person) => (
                        <SelectItem key={person.id} value={String(person.id)}>
                            {person.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
