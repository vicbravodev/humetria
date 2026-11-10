import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { groupChoice } from '@/lib/engagement/commitments';
import type { CommitmentGroupOption } from '@/types';

/**
 * Whom the commitment is for: the whole company or a group the results show. A group with its three commitments is not
 * offered. The choice travels as the two fields the server reads.
 */
export default function CommitmentGroupField({
    groups,
    error,
}: {
    groups: CommitmentGroupOption[];
    error: string | undefined;
}) {
    const open = groups.filter((group) => !group.full);
    const [chosen, setChosen] = useState(open[0]);
    const options = open.map((group) => ({
        value: groupChoice(group),
        label: group.name,
    }));
    const first = options[0];

    return (
        <div className="grid gap-2">
            <Label htmlFor="commitment-group">Para quién es</Label>
            <input
                type="hidden"
                name="group_type"
                value={chosen?.type ?? 'company'}
            />
            <input type="hidden" name="group_id" value={chosen?.id ?? ''} />
            <Combobox
                id="commitment-group"
                name="group"
                source={{ options }}
                {...(first === undefined ? {} : { defaultValue: first })}
                placeholder="Elige un grupo"
                invalid={error !== undefined}
                describedBy="commitment-group-error"
                onChange={(option) =>
                    setChosen(
                        open.find(
                            (group) => groupChoice(group) === option.value,
                        ),
                    )
                }
            />
            <p className="text-sm text-muted-foreground">
                Solo aparecen los grupos que los resultados muestran y que aún
                no tienen sus 3 compromisos.
            </p>
            <InputError id="commitment-group-error" message={error} />
        </div>
    );
}
