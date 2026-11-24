import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { AreaExtra as Extra } from '@/lib/permission-areas';

/**
 * A permission of an area ticked apart from its level, under its row (board Roles 7c): «Ver tamizajes de la Guía I». Still
 * where the person's role already brings it or the screen is only read.
 */
export default function AreaExtra({
    extra,
    state,
    onChange,
}: {
    extra: Extra;
    state: { checked: boolean; sensitive: boolean; fromRole: boolean };
    onChange: ((checked: boolean) => void) | undefined;
}) {
    const id = `extra-${extra.permission}`;

    return (
        <div className="flex min-h-11 flex-wrap items-center gap-x-2.5 gap-y-1 rounded-lg bg-muted px-3 py-2">
            <Checkbox
                id={id}
                data-test={id}
                checked={state.checked}
                disabled={onChange === undefined || state.fromRole}
                onCheckedChange={(next) => onChange?.(next === true)}
            />
            <label htmlFor={id} className="min-w-0 flex-1 text-sm">
                {extra.label}
                {state.fromRole && (
                    <span className="text-muted-foreground"> · su rol</span>
                )}
            </label>
            {state.sensitive && <Badge variant="warning">Sensible</Badge>}
        </div>
    );
}
