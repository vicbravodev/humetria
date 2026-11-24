import { Link } from '@inertiajs/react';
import { Save } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import DeleteRoleDialog from '@/components/tenancy/delete-role-dialog';
import PermissionToggles from '@/components/tenancy/permission-toggles';
import RoleStart from '@/components/tenancy/role-start';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import TenantField from '@/components/tenancy/tenant-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRoleEditor } from '@/hooks/use-role-editor';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/roles';
import type { RoleEditorProps } from '@/types';

/**
 * The role's name and its switches by tool, then, when it adds a sensitive permission, the owner's confirmation; save, cancel
 * and, for a role already made, delete it. Support sees the same, still.
 */
export default function RoleEditorForm({
    role,
    from,
    templates,
    groups,
    can_manage,
}: RoleEditorProps) {
    const tenantSlug = useTenantSlug();
    const editor = useRoleEditor(role, from, groups);

    return (
        <form
            noValidate
            className="flex flex-col gap-8"
            onSubmit={(event) => {
                // The delete dialog's own form lives in a portal, but React still bubbles its submit up here.
                if (event.target !== event.currentTarget) {
                    return;
                }

                event.preventDefault();
                editor.save();
            }}
        >
            <div className="flex flex-col gap-5 sm:max-w-md">
                <TenantField
                    name="label"
                    label="Nombre del rol"
                    required
                    maxLength={60}
                    value={editor.data.label}
                    onChange={(event) => editor.rename(event.target.value)}
                    disabled={!can_manage}
                    error={editor.errors['label']}
                />
                {role === null && can_manage && (
                    <RoleStart templates={templates} from={from} />
                )}
            </div>
            <section aria-labelledby="role-permissions" className="grid gap-4">
                <div className="flex flex-col gap-0.5">
                    <h2 id="role-permissions" className="text-lg font-semibold">
                        Qué puede hacer
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Enciende solo lo que este rol necesita. Lo que tu plan
                        no incluye queda apagado.
                    </p>
                </div>
                <PermissionToggles
                    groups={groups}
                    selection={{
                        selected: editor.data.permissions,
                        locked: [],
                    }}
                    onToggle={can_manage ? editor.toggle : undefined}
                />
                <InputError
                    id="role-permissions-error"
                    message={editor.errors['permissions']}
                />
            </section>
            <SensitiveConfirm
                labels={editor.sensitive}
                checked={editor.data.acknowledge_sensitive}
                onChange={editor.acknowledge}
                error={editor.errors['acknowledge_sensitive']}
            />
            {can_manage && (
                <div className="flex flex-wrap gap-2.5">
                    <Button type="submit" disabled={editor.processing}>
                        {editor.processing ? <Spinner /> : <Save aria-hidden />}
                        {role === null ? 'Crear rol' : 'Guardar cambios'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index(tenantSlug)}>Cancelar</Link>
                    </Button>
                    {role !== null && (
                        <div className="sm:ml-auto">
                            <DeleteRoleDialog role={role} />
                        </div>
                    )}
                </div>
            )}
        </form>
    );
}
