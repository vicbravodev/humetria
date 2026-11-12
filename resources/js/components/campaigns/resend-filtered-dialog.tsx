import type { ReactElement } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useReminderWait } from '@/hooks/use-reminder-wait';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { peopleQuery } from '@/lib/campaigns';
import { remindable, reminderNote } from '@/lib/evaluation';
import { countOf } from '@/lib/plural';
import { resend } from '@/routes/tenant/campaigns';
import type { PeopleFilters } from '@/types';

/**
 * Sends a new link to the people the filters show, after saying their old link stops working. Only while the list shows
 * pending people, or those whose link failed: then how many get one is exactly how many are listed.
 */
export default function ResendFilteredDialog({
    campaignId,
    filters,
    count,
    trigger,
}: {
    campaignId: string;
    filters: PeopleFilters;
    count: number;
    /** The button that opens it; "Reenviar a N personas" when not given. */
    trigger?: ReactElement;
}) {
    const tenantSlug = useTenantSlug();
    const people = countOf(count, 'persona', 'personas');
    const wait = useReminderWait();
    // Only a reminder to pending people waits: the retry of failed links goes whenever.
    const held = filters.status === 'pending' && wait.hours !== null;

    if (!remindable(filters.status, count)) {
        return null;
    }

    return (
        <ConfirmDialog
            trigger={
                trigger ?? (
                    <Button variant="outline">Reenviar a {people}</Button>
                )
            }
            title="¿Reenviar el enlace?"
            description={`Se reenvía su enlace a ${people}. Es el mismo de antes: el que ya tienen sigue sirviendo.`}
            confirmLabel="Reenviar enlaces"
            destructive={false}
            form={resend.form([tenantSlug, campaignId])}
            transform={() => peopleQuery(filters)}
            preserveScroll
            disabled={held}
        >
            {filters.status === 'pending' && (
                <p className="text-sm text-muted-foreground">
                    {reminderNote(wait.hours, wait.lastOn)}
                </p>
            )}
        </ConfirmDialog>
    );
}
