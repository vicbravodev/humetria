import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AreaSections from '@/components/tenancy/area-sections';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { areasFor, beyondRole } from '@/lib/permission-areas';
import { ownLevels } from '@/lib/permission-floor';
import { keepsAcknowledgement, sensitiveAdded } from '@/lib/permission-groups';
import { countOf } from '@/lib/plural';
import type { PermissionGroup } from '@/types';

/**
 * «Permisos adicionales (opcional)» of an invitation, folded until asked for: the same areas as a role's, with the chosen role's
 * levels as the floor; what goes past them is given when the person accepts. They go with the form as `permissions[]`; a
 * sensitive one asks for the confirmation (`acknowledge_sensitive`).
 */
export default function InviteExtraPermissions({
    groups,
    base,
    errors,
}: {
    groups: PermissionGroup[];
    /** What the chosen role already switches on. */
    base: string[];
    errors: {
        permissions: string | undefined;
        acknowledge: string | undefined;
    };
}) {
    const areas = areasFor(groups).map((item) => item.area);
    // The whole level of each area raised, so changing the role never leaves one half (manage without view).
    const [chosen, setChosen] = useState<string[]>([]);
    const [acknowledged, setAcknowledged] = useState(false);
    // What the role chosen now already brings is not the person's own.
    const own = chosen.filter((name) => !base.includes(name));
    const raised = beyondRole(areas, own).length;

    return (
        <Collapsible className="flex flex-col gap-4">
            <CollapsibleTrigger
                data-test="invite-extras"
                className="group flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 text-left font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <span className="flex flex-col">
                    Permisos adicionales (opcional)
                    <span className="text-sm font-normal text-muted-foreground">
                        Lo que tendrá además de su rol
                        {raised > 0 && ` · ${countOf(raised, 'área', 'áreas')}`}
                    </span>
                </span>
                <ChevronDown
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <AreaSections
                    groups={groups}
                    held={[...base, ...own]}
                    base={base}
                    onChange={(held) => {
                        const kept = ownLevels({ areas, held, base });
                        const next = kept.filter(
                            (name) => !base.includes(name),
                        );

                        setChosen(kept);
                        // A different set of sensitive permissions asks for the confirmation again.
                        setAcknowledged(
                            (was) =>
                                was &&
                                keepsAcknowledgement(groups, [], {
                                    previous: own,
                                    next,
                                }),
                        );
                    }}
                />
            </CollapsibleContent>
            {/* Outside the folding part: folding it again keeps what was chosen in the form. */}
            {own.map((name) => (
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
                labels={sensitiveAdded(groups, [], own)}
                copy={{
                    title: 'Estás abriendo información sensible',
                    consent:
                        'Entiendo que esta persona verá datos personales y que cada consulta queda registrada.',
                }}
                checked={acknowledged}
                onChange={setAcknowledged}
                error={errors.acknowledge ?? errors.permissions}
            />
        </Collapsible>
    );
}
