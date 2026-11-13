import { useState } from 'react';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { homeEntries } from '@/lib/telework/roster';
import type { HomeAddress } from '@/types';

/**
 * The home of the person (numeral 5.1). It is a datum apart from the workplace, but it starts from the first workplace
 * validated: «Es el mismo que su lugar de trabajo» sends that address, and unchecking it asks for the fields.
 */
export default function RosterHome({
    home,
    states,
    errors,
}: {
    home: HomeAddress | null;
    states: Record<string, string>;
    errors: Partial<Record<string, string>>;
}) {
    const [sameAsWork, setSameAsWork] = useState(home !== null);

    return (
        <fieldset className="grid gap-4 rounded-xl border p-4">
            <legend className="px-1 font-medium">
                Domicilio de la persona
            </legend>
            {home !== null && (
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="roster-home-same"
                        checked={sameAsWork}
                        onCheckedChange={(checked) =>
                            setSameAsWork(checked === true)
                        }
                    />
                    <Label htmlFor="roster-home-same">
                        Es el mismo que su lugar de trabajo
                    </Label>
                </div>
            )}
            {home !== null && sameAsWork ? (
                homeEntries(home).map(([name, value]) => (
                    <input key={name} type="hidden" name={name} value={value} />
                ))
            ) : (
                <WorkplaceFields index={null} states={states} errors={errors} />
            )}
        </fieldset>
    );
}
