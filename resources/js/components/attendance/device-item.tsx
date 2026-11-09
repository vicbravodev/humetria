import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    deviceKind,
    deviceUse,
    revokeDeviceText,
} from '@/lib/attendance/devices';
import { revoke } from '@/routes/tenant/attendance/devices';
import type { LinkedDevice } from '@/types';

/** One linked device: its name, the kind of key it holds, when it was linked and used; revocable while active. */
export default function DeviceItem({
    device,
    owner,
    canManage,
}: {
    device: LinkedDevice;
    owner: string;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const kind = deviceKind(device.synced);
    const active = device.revokedAt === null;

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
                        {device.label}
                    </span>
                    <Badge variant="outline" title={kind.hint}>
                        {kind.label}
                    </Badge>
                </div>
                <span className="text-caption text-muted-foreground">
                    {deviceUse(device)}
                </span>
            </div>
            {canManage && active && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghost" size="sm">
                            Revocar
                        </Button>
                    }
                    title={`¿Revocar «${device.label}»?`}
                    description={revokeDeviceText(owner, device.label)}
                    confirmLabel="Revocar dispositivo"
                    form={revoke.form({
                        tenant: tenantSlug,
                        device: device.id,
                    })}
                    preserveScroll
                />
            )}
        </li>
    );
}
