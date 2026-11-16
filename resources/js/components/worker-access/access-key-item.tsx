import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { keyKind, keyUse, revokeKeyText } from '@/lib/worker-access/access';
import { revoke } from '@/routes/tenant/worker-access';
import type { AccessKey } from '@/types';

/** One key: its name, its kind, when it was registered and used; revocable while active. */
export default function AccessKeyItem({
    accessKey,
    owner,
    canManage,
}: {
    accessKey: AccessKey;
    owner: string;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const kind = keyKind(accessKey.synced);
    const active = accessKey.revokedAt === null;

    return (
        <li className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
            <div className="flex min-w-0 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={
                            active
                                ? 'font-medium'
                                : 'text-muted-foreground line-through'
                        }
                    >
                        {accessKey.label}
                    </span>
                    <Badge variant="outline" title={kind.hint}>
                        {kind.label}
                    </Badge>
                </div>
                <span className="text-caption text-muted-foreground">
                    {keyUse(accessKey)}
                </span>
            </div>
            {canManage && active && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghost" size="sm">
                            Revocar
                        </Button>
                    }
                    title={`¿Revocar «${accessKey.label}»?`}
                    description={revokeKeyText(owner, accessKey.label)}
                    confirmLabel="Revocar llave"
                    form={revoke.form({
                        tenant: tenantSlug,
                        credential: accessKey.id,
                    })}
                    preserveScroll
                />
            )}
        </li>
    );
}
