import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DEMO_CHOICES } from '@/lib/demo-requests';
import type { DemoRequestRow } from '@/types';

/**
 * Where the request stands. A converted request keeps its status, and a scheduled one may keep it: neither is chosen by
 * hand, so they are offered only to the request already in them.
 */
export default function DemoStatusField({
    request,
    error,
}: {
    request: DemoRequestRow;
    error: string | undefined;
}) {
    if (request.status === 'converted') {
        return <input type="hidden" name="status" value="converted" />;
    }

    const choices =
        request.status === 'scheduled'
            ? [{ value: 'scheduled', label: 'Demo agendada' }, ...DEMO_CHOICES]
            : DEMO_CHOICES;

    return (
        <div className="grid gap-2">
            <Label htmlFor={`status-${request.id}`}>Estado</Label>
            <Select name="status" defaultValue={request.status}>
                <SelectTrigger id={`status-${request.id}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {choices.map((choice) => (
                        <SelectItem key={choice.value} value={choice.value}>
                            {choice.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
