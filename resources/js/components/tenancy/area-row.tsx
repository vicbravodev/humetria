import AreaTitle from '@/components/tenancy/area-title';
import AreaExtra from '@/components/tenancy/area-extra';
import LevelControl from '@/components/tenancy/level-control';
import { roleMix } from '@/lib/permission-floor';
import { toggled } from '@/lib/permission-groups';
import {
    floorOf,
    levelOf,
    rowTags,
    sensitiveLevel,
    withLevel,
} from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';
import type { PermissionGroup } from '@/types';

/**
 * One area of the roles screen (board Roles 7c): its name, what its level lets do, the level and its extras. «Sensible» where
 * the level chosen opens confidential data; locked with its reason where the plan lacks the tool. With `base` (a person's
 * role) its level is the floor and what goes past it says «Adicional». Without `onChange` it is only read.
 */
export default function AreaRow({
    item,
    held,
    context,
    onChange,
}: {
    item: { area: PermissionArea; reason: string | null };
    held: string[];
    context: { groups: PermissionGroup[]; base: string[] | undefined };
    onChange: ((next: string[]) => void) | undefined;
}) {
    const { area, reason } = item;
    const { groups, base = [] } = context;
    // The role itself holds a mix no level names: nothing to raise from, the role is what to change.
    const mixed = context.base !== undefined && roleMix(area, base);
    const change = reason === null && !mixed ? onChange : undefined;
    const value = levelOf(area, held);
    const tags = rowTags({ area, held, base: context.base, groups });

    return (
        <li className="flex flex-col gap-3 px-4 py-3.5 sm:px-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <AreaTitle
                    area={area}
                    held={held}
                    groups={groups}
                    reason={reason}
                    tags={{ ...tags, mixed }}
                />
                <LevelControl
                    name={area.key}
                    label={area.label}
                    levels={area.levels}
                    value={value}
                    floor={
                        context.base === undefined
                            ? undefined
                            : floorOf(area, base)
                    }
                    onChange={
                        change === undefined
                            ? undefined
                            : (level) =>
                                  change(
                                      withLevel({
                                          selected: held,
                                          area,
                                          level,
                                      }),
                                  )
                    }
                />
            </div>
            {reason === null &&
                area.extras.map((extra) => (
                    <AreaExtra
                        key={extra.permission}
                        extra={extra}
                        state={{
                            checked: held.includes(extra.permission),
                            sensitive: sensitiveLevel(
                                { permissions: [extra.permission] },
                                groups,
                            ),
                            fromRole: base.includes(extra.permission),
                        }}
                        onChange={
                            change === undefined
                                ? undefined
                                : (on) =>
                                      change(
                                          toggled(held, extra.permission, on),
                                      )
                        }
                    />
                ))}
        </li>
    );
}
