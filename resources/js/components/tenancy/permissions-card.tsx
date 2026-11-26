import PermissionHeld from '@/components/tenancy/permission-held';
import type { PlatformPermissionRow } from '@/types';

/** «Qué puede hacer cada rol» beside the team (tablero Equipo): the same gates as «Permisos por rol», in a narrow card. */
export default function PermissionsCard({
    permissions,
}: {
    permissions: PlatformPermissionRow[];
}) {
    return (
        <section aria-labelledby="permissions-card" className="card">
            <h2
                id="permissions-card"
                className="border-b px-6 py-4 font-semibold"
            >
                Qué puede hacer cada rol
            </h2>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-caption tracking-wide text-muted-foreground uppercase">
                        <th className="py-3 pl-6 text-left font-medium">
                            Acción
                        </th>
                        <th className="w-24 py-3 font-medium">Soporte</th>
                        <th className="w-28 py-3 pr-6 font-medium">
                            Superadmin
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {permissions.map((permission) => (
                        <tr key={permission.ability} className="border-t">
                            <td className="py-3 pl-6">{permission.label}</td>
                            <td className="py-3">
                                <PermissionHeld
                                    held={permission.support}
                                    compact
                                />
                            </td>
                            <td className="py-3 pr-6">
                                <PermissionHeld
                                    held={permission.superadmin}
                                    compact
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}
