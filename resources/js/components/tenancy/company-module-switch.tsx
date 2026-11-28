import GrantModuleDialog from '@/components/tenancy/grant-module-dialog';
import RevokeModuleDialog from '@/components/tenancy/revoke-module-dialog';
import Track from '@/components/tenancy/switch-track';
import { moduleNote } from '@/lib/company-360';
import type { ModuleRow } from '@/types';

/**
 * One module of the summary (tablero Empresa 360): a switch, its name and where it comes from. For the superadmin the switch
 * opens the dialog that asks the reason, never flips it at once; for the rest of the staff it only shows the state.
 */
export default function CompanyModuleSwitch({
    companyId,
    module,
    canManage,
}: {
    companyId: string;
    module: ModuleRow;
    canManage: boolean;
}) {
    const label = (
        <span className="grid min-w-0 text-left">
            <span className="font-medium">{module.label}</span>
            <span className="text-caption text-muted-foreground">
                {moduleNote(module)}
            </span>
        </span>
    );

    if (!canManage || !module.exists_in_code) {
        return (
            <div className="flex items-center gap-3 px-6 py-3.5">
                <Track on={module.enabled} />
                <span className="sr-only">
                    {module.enabled ? 'Habilitado:' : 'No habilitado:'}
                </span>
                {label}
            </div>
        );
    }

    const trigger = (open: () => void) => (
        <button
            type="button"
            role="switch"
            aria-checked={module.enabled}
            onClick={open}
            className="flex w-full items-center gap-3 px-6 py-3.5 transition-colors hover:bg-muted/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
        >
            <Track on={module.enabled} />
            {label}
        </button>
    );
    const Dialog = module.enabled ? RevokeModuleDialog : GrantModuleDialog;

    return <Dialog companyId={companyId} module={module} trigger={trigger} />;
}
