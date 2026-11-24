import { Link } from '@inertiajs/react';
import { Save } from 'lucide-react';
import PermissionToggles from '@/components/tenancy/permission-toggles';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useMemberPermissions } from '@/hooks/use-member-permissions';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import type { PermissionGroup } from '@/types';

/** The switches of one person: their role's on and locked, the ones they have besides it to switch; then save. */
export default function MemberPermissionsForm({
    memberId,
    groups,
    canManage,
}: {
    memberId: number;
    groups: PermissionGroup[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const editor = useMemberPermissions(memberId, groups);

    return (
        <form
            noValidate
            className="flex flex-col gap-8"
            onSubmit={(event) => {
                event.preventDefault();
                editor.save();
            }}
        >
            <PermissionToggles
                groups={groups}
                selection={{
                    selected: editor.data.permissions,
                    locked: editor.locked,
                }}
                onToggle={canManage ? editor.toggle : undefined}
            />
            <SensitiveConfirm
                copy={{
                    title: 'Estás abriendo información sensible',
                    consent:
                        'Entiendo que esta persona verá datos personales y que cada consulta queda registrada.',
                }}
                labels={editor.sensitive}
                checked={editor.data.acknowledge_sensitive}
                onChange={editor.acknowledge}
                error={editor.errors['acknowledge_sensitive']}
            />
            {canManage && (
                <div className="flex flex-wrap gap-2.5">
                    <Button type="submit" disabled={editor.processing}>
                        {editor.processing ? <Spinner /> : <Save aria-hidden />}
                        Guardar permisos
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index(tenantSlug)}>Cancelar</Link>
                    </Button>
                </div>
            )}
        </form>
    );
}
