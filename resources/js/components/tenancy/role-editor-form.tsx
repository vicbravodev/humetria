import InputError from '@/components/humetria/input-error';
import AreaSections from '@/components/tenancy/area-sections';
import RoleEditorSummary from '@/components/tenancy/role-editor-summary';
import RolePresets from '@/components/tenancy/role-presets';
import SaveBar from '@/components/tenancy/save-bar';
import TenantField from '@/components/tenancy/tenant-field';
import { useRoleEditor } from '@/hooks/use-role-editor';
import { accessCount, areasFor } from '@/lib/permission-areas';
import type { RoleEditorProps } from '@/types';

/**
 * The role's name, where it starts from (a new one), its areas by group and, beside them, the summary with the confirmation of
 * sensitive permissions and the buttons; on a phone the save button stays at the bottom. Support sees the same, still.
 */
export default function RoleEditorForm({
    role,
    from,
    templates,
    groups,
    can_manage,
}: RoleEditorProps) {
    const editor = useRoleEditor(role, from, groups);
    const count = accessCount(
        areasFor(groups).map((item) => item.area),
        editor.data.permissions,
    );

    return (
        <form
            noValidate
            className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]"
            onSubmit={(event) => {
                event.preventDefault();
                editor.save(event.currentTarget);
            }}
        >
            <div className="flex min-w-0 flex-col gap-7">
                <div className="sm:max-w-md">
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
                </div>
                {can_manage && (
                    <RolePresets
                        templates={templates}
                        value={editor.preset}
                        editing={role !== null}
                        onChoose={editor.choose}
                    />
                )}
                <AreaSections
                    groups={groups}
                    held={editor.data.permissions}
                    onChange={can_manage ? editor.change : undefined}
                />
                <InputError
                    id="role-permissions-error"
                    message={editor.errors['permissions']}
                />
            </div>
            <RoleEditorSummary
                role={role}
                groups={groups}
                editor={editor}
                canManage={can_manage}
            />
            {can_manage && (
                <SaveBar
                    status={
                        <>
                            <strong className="font-semibold text-foreground tabular-nums">
                                {count.on} de {count.total}
                            </strong>{' '}
                            áreas con acceso
                        </>
                    }
                    label={role === null ? 'Crear rol' : 'Guardar rol'}
                    processing={editor.processing}
                />
            )}
        </form>
    );
}
