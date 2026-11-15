import GrantModuleDialog from '@/components/tenancy/grant-module-dialog';
import RevokeModuleDialog from '@/components/tenancy/revoke-module-dialog';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import type { ModuleRow } from '@/types';

/** A module of the company: its name, whether it is on, where that comes from, and, for the superadmin, the way to change it. */
export default function ModuleRowView({
    companyId,
    module,
    canManage,
}: {
    companyId: string;
    module: ModuleRow;
    canManage: boolean;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-48 whitespace-normal">
                <div className="flex flex-col">
                    <span className="font-medium">{module.label}</span>
                    {!module.exists_in_code && (
                        <span className="text-caption text-muted-foreground">
                            Aún no está disponible
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={module.enabled ? 'default' : 'outline'}>
                    {module.enabled ? 'Habilitado' : 'No habilitado'}
                </Badge>
            </TableCell>
            <TableCell className="min-w-56 text-sm whitespace-normal">
                {module.source_label ?? 'Sin origen'}
                {module.ends_on !== null && (
                    <span className="block text-caption text-muted-foreground">
                        {module.enabled
                            ? `Hasta el ${module.ends_on}`
                            : `Desde el ${module.ends_on}`}
                    </span>
                )}
                {module.reason !== null && (
                    <span className="block text-caption text-muted-foreground">
                        Motivo: {module.reason}
                    </span>
                )}
            </TableCell>
            <TableCell className="w-px text-right">
                {canManage &&
                    (module.enabled ? (
                        <RevokeModuleDialog
                            companyId={companyId}
                            module={module}
                        />
                    ) : (
                        <GrantModuleDialog
                            companyId={companyId}
                            module={module}
                        />
                    ))}
            </TableCell>
        </TableRow>
    );
}
