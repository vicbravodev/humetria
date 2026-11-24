import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import PermissionToggles from '@/components/tenancy/permission-toggles';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    keepsAcknowledgement,
    sensitiveAdded,
    toggled,
} from '@/lib/permission-groups';
import { countOf } from '@/lib/plural';
import type { PermissionGroup } from '@/types';

/**
 * «Permisos adicionales» of an invitation, folded until asked for: what the person will have besides the role, given when they
 * accept. They go with the form as `permissions[]`; a sensitive one asks for «Entiendo y confirmo» (`acknowledge_sensitive`).
 */
export default function InviteExtraPermissions({
    groups,
    errors,
}: {
    groups: PermissionGroup[];
    errors: {
        permissions: string | undefined;
        acknowledge: string | undefined;
    };
}) {
    const [selected, setSelected] = useState<string[]>([]);
    const [acknowledged, setAcknowledged] = useState(false);

    return (
        <Collapsible className="flex flex-col gap-4">
            <CollapsibleTrigger
                data-test="invite-extras"
                className="group flex items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 text-left font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <span className="flex flex-col">
                    Permisos adicionales
                    <span className="text-sm font-normal text-muted-foreground">
                        Opcional: lo que tendrá además de su rol
                        {selected.length > 0 &&
                            ` · ${countOf(selected.length, 'elegido', 'elegidos')}`}
                    </span>
                </span>
                <ChevronDown
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="flex flex-col gap-4">
                <PermissionToggles
                    groups={groups}
                    selection={{ selected, locked: [] }}
                    onToggle={(name, on) => {
                        const next = toggled(selected, name, on);

                        setSelected(next);
                        // A different set of sensitive permissions asks for the confirmation again.
                        setAcknowledged(
                            (was) =>
                                was &&
                                keepsAcknowledgement(groups, [], {
                                    previous: selected,
                                    next,
                                }),
                        );
                    }}
                    columns={1}
                />
            </CollapsibleContent>
            {/* Outside the folding part: folding it again keeps what was chosen in the form. */}
            {selected.map((name) => (
                <input
                    key={name}
                    type="hidden"
                    name="permissions[]"
                    value={name}
                />
            ))}
            {acknowledged && (
                <input type="hidden" name="acknowledge_sensitive" value="1" />
            )}
            <SensitiveConfirm
                copy={{
                    title: 'Estás abriendo información sensible',
                    consent:
                        'Entiendo que esta persona verá datos personales y que cada consulta queda registrada.',
                }}
                labels={sensitiveAdded(groups, [], selected)}
                checked={acknowledged}
                onChange={setAcknowledged}
                error={errors.acknowledge ?? errors.permissions}
            />
        </Collapsible>
    );
}
