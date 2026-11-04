import ActionSheet from '@/components/interventions/action-sheet';
import CancelDialog from '@/components/interventions/cancel-dialog';
import CopyDialog from '@/components/interventions/copy-dialog';
import HistoryDialog from '@/components/interventions/history-dialog';
import ProgressDialog from '@/components/interventions/progress-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/campaigns/program/actions';
import type {
    ActionTool,
    ProgramAction,
    ProgramRow,
    ProgramTools,
} from '@/types';

/** The dialog or side panel of the tool chosen in an action's menu; none while nothing is chosen. */
export default function ActionDialogs({
    opened,
    onClose,
    action,
    campaignId,
    rows,
    tools,
}: {
    opened: ActionTool | null;
    onClose: (open: boolean) => void;
    action: ProgramAction;
    campaignId: string;
    rows: ProgramRow[];
    tools: ProgramTools | null;
}) {
    const tenantSlug = useTenantSlug();

    switch (opened) {
        case 'history':
            return (
                <HistoryDialog open onOpenChange={onClose} action={action} />
            );
        case 'copy':
            return (
                <CopyDialog
                    open
                    onOpenChange={onClose}
                    campaignId={campaignId}
                    action={action}
                    rows={rows}
                />
            );
        case 'cancel':
            return (
                <CancelDialog
                    open
                    onOpenChange={onClose}
                    campaignId={campaignId}
                    action={action}
                />
            );
        case 'progress':
            return tools === null ? null : (
                <ProgressDialog
                    open
                    onOpenChange={onClose}
                    campaignId={campaignId}
                    action={action}
                    today={tools.today}
                />
            );
        case 'edit':
            return tools === null ? null : (
                <ActionSheet
                    open
                    onOpenChange={onClose}
                    form={update.form([tenantSlug, campaignId, action.id])}
                    itemId={action.itemId}
                    itemLabel={action.itemLabel}
                    action={action}
                    tools={tools}
                />
            );
        case null:
            return null;
    }
}
