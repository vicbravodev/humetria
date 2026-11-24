import { Link } from '@inertiajs/react';
import AccessSummary from '@/components/tenancy/access-summary';
import DeleteRoleDialog from '@/components/tenancy/delete-role-dialog';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { useRoleEditor } from '@/hooks/use-role-editor';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { accessCount, areasFor, summarize } from '@/lib/permission-areas';
import { plural } from '@/lib/plural';
import { index } from '@/routes/tenant/roles';
import type { PermissionGroup, RoleEditorProps } from '@/types';

/**
 * The editor's «Resumen» (board Roles 7c): what whoever holds the role can do, how many areas it opens and who holds it, the
 * confirmation when it adds sensitive permissions, then save, cancel and, for a role already made, delete it.
 */
export default function RoleEditorSummary({
    role,
    groups,
    editor,
    canManage,
}: {
    role: RoleEditorProps['role'];
    groups: PermissionGroup[];
    editor: ReturnType<typeof useRoleEditor>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const areas = areasFor(groups).map((item) => item.area);
    const held = editor.data.permissions;
    const count = accessCount(areas, held);

    return (
        <AccessSummary
            sentence={summarize({
                areas,
                held,
                subject: 'Quien tenga este rol',
            })}
            facts={[
                {
                    label: 'Áreas con acceso',
                    value: `${count.on} de ${count.total}`,
                },
                ...(role === null
                    ? []
                    : [
                          {
                              label: 'Personas con este rol',
                              value: String(role.people),
                          },
                      ]),
            ]}
        >
            <SensitiveConfirm
                labels={editor.sensitive}
                copy={{
                    title: 'Este rol abre información sensible',
                    consent:
                        'Entiendo que quienes tengan este rol verán datos personales y que cada consulta queda registrada.',
                }}
                checked={editor.data.acknowledge_sensitive}
                onChange={editor.acknowledge}
                error={editor.errors['acknowledge_sensitive']}
            />
            {canManage && (
                <div className="flex flex-wrap gap-2 max-lg:hidden">
                    <Button type="submit" disabled={editor.processing}>
                        {editor.processing && <Spinner />}
                        {role === null ? 'Crear rol' : 'Guardar rol'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index(tenantSlug)}>Cancelar</Link>
                    </Button>
                </div>
            )}
            {role !== null && role.people > 0 && (
                <p className="text-sm text-muted-foreground">
                    {plural(
                        role.people,
                        'Los cambios aplican a la persona con este rol en cuanto guardes.',
                        `Los cambios aplican a las ${role.people} personas con este rol en cuanto guardes.`,
                    )}
                </p>
            )}
            {canManage && role !== null && (
                <div className="border-t pt-4">
                    <DeleteRoleDialog role={role} />
                </div>
            )}
        </AccessSummary>
    );
}
