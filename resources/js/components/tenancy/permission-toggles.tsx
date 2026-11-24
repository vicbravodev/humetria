import PermissionSwitch from '@/components/tenancy/permission-switch';
import { groupCount } from '@/lib/permission-groups';
import { cn } from '@/lib/utils';
import type { PermissionGroup } from '@/types';

/**
 * The switches of the roles screen, one card per tool with how many it has on («2 de 3»). `locked` are on because the person's
 * role brings them; without `onToggle` the switches are only read.
 */
export default function PermissionToggles({
    groups,
    selection,
    onToggle,
    columns = 2,
}: {
    groups: PermissionGroup[];
    selection: { selected: string[]; locked: string[] };
    onToggle?: ((name: string, on: boolean) => void) | undefined;
    /** Two side by side on a wide screen; one inside a narrow form. */
    columns?: 1 | 2;
}) {
    return (
        <div
            className={cn(
                'grid items-start gap-4',
                columns === 2 && 'lg:grid-cols-2',
            )}
        >
            {groups.map((group) => {
                const { on, total } = groupCount(
                    group,
                    selection.selected,
                    selection.locked,
                );

                return (
                    <section
                        key={group.module}
                        aria-labelledby={`group-${group.module}`}
                        className="overflow-hidden card"
                    >
                        <header className="flex items-center justify-between gap-3 border-b bg-muted px-5 py-3">
                            <h3
                                id={`group-${group.module}`}
                                className="font-semibold"
                            >
                                {group.label}
                            </h3>
                            {total > 0 && (
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {on} de {total}
                                </span>
                            )}
                        </header>
                        <ul className="divide-y">
                            {group.permissions.map((permission) => (
                                <PermissionSwitch
                                    key={permission.name}
                                    permission={permission}
                                    state={{
                                        on: [
                                            ...selection.selected,
                                            ...selection.locked,
                                        ].includes(permission.name),
                                        locked: selection.locked.includes(
                                            permission.name,
                                        ),
                                    }}
                                    onChange={
                                        onToggle === undefined
                                            ? undefined
                                            : (next) =>
                                                  onToggle(
                                                      permission.name,
                                                      next,
                                                  )
                                    }
                                />
                            ))}
                        </ul>
                    </section>
                );
            })}
        </div>
    );
}
