import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import type { MexicanStateOption } from '@/types';

/** The state, searched among the 32 by typing (accents optional). Its value goes in the form as `state`. */
export default function WorkCenterStateField({
    states,
    defaultValue,
    error,
    onChange,
}: {
    states: MexicanStateOption[];
    defaultValue: string | undefined;
    error: string | undefined;
    /** Called with each state chosen, for the zone to follow it. */
    onChange: (state: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="work-center-state">Estado</Label>
            <Combobox
                id="work-center-state"
                name="state"
                source={{ options: states }}
                defaultValue={states.find(
                    ({ value }) => value === defaultValue,
                )}
                placeholder="Elige un estado"
                onChange={({ value }) => onChange(value)}
                invalid={error !== undefined}
                describedBy={
                    error === undefined ? undefined : 'work-center-state-error'
                }
            />
            <InputError id="work-center-state-error" message={error} />
        </div>
    );
}
