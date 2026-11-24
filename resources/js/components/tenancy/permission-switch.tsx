import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { switchLook } from '@/lib/permission-groups';
import { cn } from '@/lib/utils';
import type { PermissionToggle } from '@/types';

/**
 * One permission: what it lets do, «Sensible» where it opens confidential data, and its switch. Off and still with its reason
 * where the plan lacks its tool; on and still where the person's role already brings it («Viene de su rol»). `onChange`
 * missing means the screen is only read.
 */
export default function PermissionSwitch({
    permission,
    state,
    onChange,
}: {
    permission: PermissionToggle;
    state: { on: boolean; locked: boolean };
    onChange: ((on: boolean) => void) | undefined;
}) {
    const id = `permission-${permission.name}`;
    const { on, still, hint } = switchLook(
        permission,
        state,
        onChange === undefined,
    );

    return (
        <li className="flex items-start justify-between gap-4 px-5 py-3.5">
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="flex flex-wrap items-center gap-2">
                    <span id={id} className="font-medium">
                        {permission.label}
                    </span>
                    {permission.sensitive && (
                        <Badge variant="warning">Sensible</Badge>
                    )}
                </span>
                <span
                    id={`${id}-hint`}
                    className="text-sm text-pretty text-muted-foreground"
                >
                    {permission.description}
                    {hint !== null && (
                        <span className="mt-0.5 flex items-center gap-1.5 font-medium text-foreground">
                            <Lock aria-hidden className="size-3.5 shrink-0" />
                            {hint}
                        </span>
                    )}
                </span>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={on}
                aria-labelledby={id}
                aria-describedby={`${id}-hint`}
                disabled={still}
                data-test={id}
                onClick={() => onChange?.(!on)}
                className={cn(
                    'touch-hit relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed',
                    on ? 'bg-primary' : 'bg-input',
                    still && 'opacity-60',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'inline-block size-5 rounded-full bg-background shadow-raised transition-transform',
                        on ? 'translate-x-5' : 'translate-x-0',
                    )}
                />
            </button>
        </li>
    );
}
