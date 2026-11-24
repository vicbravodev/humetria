import { Lock } from 'lucide-react';
import AreaExtra from '@/components/tenancy/area-extra';
import LevelControl from '@/components/tenancy/level-control';
import { Badge } from '@/components/ui/badge';
import { toggled } from '@/lib/permission-groups';
import {
    beyondRole,
    floorOf,
    levelOf,
    phraseOf,
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
    const change = reason === null ? onChange : undefined;
    const value = levelOf(area, held);
    const index = area.levels.findIndex((level) => level.key === value);
    const floor = floorOf(area, base);
    const additional =
        context.base !== undefined &&
        beyondRole(
            [area],
            held.filter((name) => !base.includes(name)),
        ).length > 0;

    return (
        <li className="flex flex-col gap-3 px-4 py-3.5 sm:px-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="flex flex-wrap items-center gap-2 font-medium">
                        {area.label}
                        {additional && <Badge>Adicional</Badge>}
                        {index > floor &&
                            sensitiveLevel(area.levels[index], groups) && (
                                <Badge variant="warning">Sensible</Badge>
                            )}
                    </span>
                    <span className="text-sm text-pretty text-muted-foreground">
                        {reason === null ? (
                            phraseOf(area, held, groups)
                        ) : (
                            <span className="flex items-center gap-1.5">
                                <Lock
                                    aria-hidden
                                    className="size-3.5 shrink-0"
                                />
                                {reason}
                            </span>
                        )}
                    </span>
                </div>
                <LevelControl
                    name={area.key}
                    label={area.label}
                    levels={area.levels}
                    value={value}
                    floor={floor}
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
