import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { index } from '@/routes/tenant/attendance/corrections';

/** Above the marks to verify: choose the whole page (whoever resolves) and show only the marks with no flags. */
export default function OfflineMarkToolbar({
    q,
    unflagged,
    canResolve,
    all,
    onToggleAll,
}: {
    q: string;
    unflagged: boolean;
    canResolve: boolean;
    all: boolean;
    onToggleAll: (checked: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            {canResolve ? (
                <div className="flex items-center gap-2.5">
                    <Checkbox
                        id="offline-select-page"
                        checked={all}
                        onCheckedChange={(value) => onToggleAll(value === true)}
                    />
                    <Label htmlFor="offline-select-page">
                        Seleccionar la página
                    </Label>
                </div>
            ) : (
                <span />
            )}
            <Button
                asChild
                size="sm"
                variant={unflagged ? 'default' : 'outline'}
            >
                <Link
                    href={index(tenantSlug, {
                        query: correctionsQuery({
                            q,
                            status: 'offline',
                            flags: unflagged ? '' : 'none',
                        }),
                    })}
                    aria-current={unflagged ? 'true' : undefined}
                >
                    Sin banderas
                </Link>
            </Button>
        </div>
    );
}
