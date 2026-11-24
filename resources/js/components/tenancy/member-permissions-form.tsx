import { Link } from '@inertiajs/react';
import AccessSummary from '@/components/tenancy/access-summary';
import AreaSections from '@/components/tenancy/area-sections';
import SaveBar from '@/components/tenancy/save-bar';
import SensitiveConfirm from '@/components/tenancy/sensitive-confirm';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useMemberPermissions } from '@/hooks/use-member-permissions';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { areasFor, beyondRole, summarize } from '@/lib/permission-areas';
import { countOf } from '@/lib/plural';
import { index } from '@/routes/tenant/members';
import type { PermissionGroup } from '@/types';

/**
 * The areas of one person (board Roles 7c, Persona): their role's level is the floor, locked under it; what they get past it
 * says «Adicional». Beside, the summary of what they have besides the role, the confirmation of sensitive ones and save.
 */
export default function MemberPermissionsForm({
    member,
    groups,
    canManage,
}: {
    member: { id: number; name: string; role_label: string | null };
    groups: PermissionGroup[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const editor = useMemberPermissions(member.id, groups);
    const raised = beyondRole(
        areasFor(groups).map((item) => item.area),
        editor.data.permissions,
    );
    const extra = countOf(raised.length, 'área', 'áreas');

    return (
        <form
            noValidate
            className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]"
            onSubmit={(event) => {
                event.preventDefault();
                editor.save();
            }}
        >
            <AreaSections
                groups={groups}
                held={editor.held}
                base={editor.base}
                onChange={canManage ? editor.change : undefined}
            />
            <AccessSummary
                sentence={
                    raised.length === 0
                        ? 'Por ahora solo tiene lo que trae su rol.'
                        : summarize({
                              areas: raised,
                              held: editor.held,
                              subject: `Además de lo que trae su rol, ${member.name}`,
                          })
                }
                facts={[
                    { label: 'Rol', value: member.role_label ?? 'Sin rol' },
                    { label: 'Permisos adicionales', value: extra },
                ]}
            >
                <SensitiveConfirm
                    labels={editor.sensitive}
                    copy={{
                        title: 'Estás abriendo información sensible',
                        consent: `Entiendo que ${member.name} verá datos personales y que cada consulta queda registrada.`,
                    }}
                    checked={editor.data.acknowledge_sensitive}
                    onChange={editor.acknowledge}
                    error={editor.errors['acknowledge_sensitive']}
                />
                {canManage && (
                    <div className="flex flex-wrap gap-2 max-lg:hidden">
                        <Button type="submit" disabled={editor.processing}>
                            {editor.processing && <Spinner />}
                            Guardar permisos
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>Cancelar</Link>
                        </Button>
                    </div>
                )}
            </AccessSummary>
            {canManage && (
                <SaveBar
                    status={
                        <>
                            Permisos adicionales:{' '}
                            <strong className="font-semibold text-foreground">
                                {extra}
                            </strong>
                        </>
                    }
                    label="Guardar permisos"
                    processing={editor.processing}
                />
            )}
        </form>
    );
}
