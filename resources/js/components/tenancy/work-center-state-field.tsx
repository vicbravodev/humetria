import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { MexicanStateOption } from '@/types';

/** The state, chosen from the 32; typing a letter jumps to it. Its value goes in the form as `state`. */
export default function WorkCenterStateField({
    states,
    defaultValue,
    error,
}: {
    states: MexicanStateOption[];
    defaultValue: string | undefined;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="work-center-state">Estado</Label>
            <Select name="state" defaultValue={defaultValue}>
                <SelectTrigger
                    id="work-center-state"
                    className="w-full"
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined
                            ? undefined
                            : 'work-center-state-error'
                    }
                >
                    <SelectValue placeholder="Elige un estado" />
                </SelectTrigger>
                <SelectContent>
                    {states.map((state) => (
                        <SelectItem key={state.value} value={state.value}>
                            {state.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError
                id="work-center-state-error"
                role="alert"
                message={error}
            />
        </div>
    );
}
